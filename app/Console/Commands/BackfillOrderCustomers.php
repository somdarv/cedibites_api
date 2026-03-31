<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillOrderCustomers extends Command
{
    protected $signature = 'customers:backfill-from-orders
                            {--dry-run : Preview what would be created without writing anything}';

    protected $description = 'Create User+Customer records from historic orders that have contact_phone but no customer_id';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no data will be written.');
        }

        // Fetch every unique phone from orders that have no customer_id yet
        $rows = Order::whereNull('customer_id')
            ->whereNotNull('contact_phone')
            ->where('contact_phone', '!=', '')
            ->select('contact_phone', 'contact_name', DB::raw('COUNT(*) as order_count'))
            ->groupBy('contact_phone', 'contact_name')
            ->orderBy('order_count', 'desc')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No unlinked orders found. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info("Found {$rows->count()} unique phone number(s) across unlinked orders.");

        $created  = 0;
        $merged   = 0;
        $linked   = 0;
        $skipped  = 0;

        // Consolidate: one phone may appear with different name spellings — pick the most common
        $byPhone = $rows->groupBy('contact_phone')->map(function ($group) {
            // Use the name that appears most often for this phone
            return $group->sortByDesc('order_count')->first();
        });

        $bar = $this->output->createProgressBar($byPhone->count());
        $bar->start();

        foreach ($byPhone as $phone => $row) {
            $name = $row->contact_name ?? 'Walk-in Customer';

            try {
                DB::transaction(function () use ($phone, $name, $dryRun, &$created, &$merged, &$linked) {
                    // Find or create a User for this phone
                    $user = User::where('phone', $phone)->first();

                    if (! $user) {
                        if (! $dryRun) {
                            $user = User::create([
                                'phone' => $phone,
                                'name'  => $name,
                                // password intentionally null — walk-in customer, not a registered user
                            ]);
                        }
                        $created++;
                    } else {
                        $merged++;
                    }

                    if ($dryRun) {
                        $linked += Order::whereNull('customer_id')
                            ->where('contact_phone', $phone)
                            ->count();
                        return;
                    }

                    // Ensure a Customer record exists
                    if (! $user->customer) {
                        $user->customer()->create(['is_guest' => true]);
                        $user->load('customer');
                    }

                    $customerId = $user->customer->id;

                    // Link all unlinked orders for this phone to the customer
                    $count = Order::whereNull('customer_id')
                        ->where('contact_phone', $phone)
                        ->update(['customer_id' => $customerId]);

                    $linked += $count;
                });
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("  Failed for phone {$phone}: {$e->getMessage()}");
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info("DRY RUN results (nothing was written):");
            $this->line("  Would create  : {$created} new user(s)");
            $this->line("  Would merge   : {$merged} existing user(s)");
            $this->line("  Would link    : {$linked} order(s)");
        } else {
            $this->info("Backfill complete:");
            $this->line("  Users created : {$created}");
            $this->line("  Users merged  : {$merged}");
            $this->line("  Orders linked : {$linked}");
            if ($skipped > 0) {
                $this->warn("  Skipped       : {$skipped} phone(s) due to errors — check logs.");
            }
        }

        return self::SUCCESS;
    }
}
