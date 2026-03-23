<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\MenuItemRating;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\ShiftOrder;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PrepareProduction extends Command
{
    protected $signature = 'app:prepare-production
                            {--force : Skip confirmation prompt}';

    protected $description = 'Strip all dummy/seeder data, keeping branches, menus, promos, roles and the admin account';

    public function handle(): int
    {
        $this->warn('⚠  This will permanently delete all transactional and dummy data.');
        $this->line('   Branches, menus, add-ons, tags, promos, roles and the admin account will be preserved.');
        $this->newLine();

        if (! $this->option('force') && ! $this->confirm('Are you sure you want to continue?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Preparing production environment...');

        Schema::disableForeignKeyConstraints();

        DB::transaction(function () {
            $this->deleteTransactionalData();
            $this->truncateOperationalTables();
        });

        Schema::enableForeignKeyConstraints();

        $this->newLine();
        $this->warn('⚠  Remember to change the admin password before going live:');
        $this->line('   Email: admin@cedibites.com');
        $this->newLine();
        $this->line('   Run: php artisan optimize');
        $this->newLine();
        $this->info('Done. Production environment is ready.');

        return self::SUCCESS;
    }

    private function deleteTransactionalData(): void
    {
        $this->deleted('shift_orders', ShiftOrder::query()->forceDelete());
        $this->deleted('shifts', Shift::query()->forceDelete());
        $this->deleted('cart_items', CartItem::query()->forceDelete());
        $this->deleted('carts', Cart::query()->forceDelete());
        $this->deleted('payments', Payment::query()->forceDelete());
        $this->deleted('order_status_history', OrderStatusHistory::query()->forceDelete());
        $this->deleted('order_items', OrderItem::query()->forceDelete());
        $this->deleted('orders', Order::query()->forceDelete());
        $this->deleted('menu_item_ratings', MenuItemRating::query()->forceDelete());
        $this->deleted('addresses', Address::query()->forceDelete());
        $this->deleted('customers', Customer::query()->forceDelete());

        $this->deleteNonAdminEmployeesAndUsers();
    }

    private function deleteNonAdminEmployeesAndUsers(): void
    {
        $adminUser = User::where('email', 'admin@cedibites.com')->first();
        $adminUserId = $adminUser?->id;

        $dummyUserIds = User::when($adminUserId, fn ($q) => $q->where('id', '!=', $adminUserId))
            ->get()
            ->pluck('id');

        $employeeCount = Employee::whereIn('user_id', $dummyUserIds)
            ->orWhereNull('user_id')
            ->when($adminUserId, fn ($q) => $q->where('user_id', '!=', $adminUserId))
            ->forceDelete();

        $this->deleted('employees (non-admin)', $employeeCount);

        $userCount = User::whereIn('id', $dummyUserIds)->forceDelete();
        $this->deleted('users (non-admin)', $userCount);
    }

    private function truncateOperationalTables(): void
    {
        foreach (['otps', 'notifications', 'sessions', 'personal_access_tokens', 'activity_log'] as $table) {
            DB::table($table)->truncate();
            $this->line("  Truncated <comment>{$table}</comment>");
        }
    }

    private function deleted(string $label, int $count): void
    {
        $this->line("  Deleted <comment>{$count}</comment> row(s) from <comment>{$label}</comment>");
    }
}
