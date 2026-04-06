<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Services\SmartCategories\SmartCategoryService;
use Illuminate\Console\Command;

/**
 * Pre-computes smart category item lists for all active branches.
 *
 * Scheduled every 6 hours to keep cached results fresh without
 * running expensive queries on every customer request.
 */
class ComputeSmartCategories extends Command
{
    protected $signature = 'menu:compute-smart-categories
                            {--branch= : Compute for a specific branch ID only}';

    protected $description = 'Pre-compute smart category item lists for all active branches';

    public function handle(SmartCategoryService $service): int
    {
        $branchQuery = Branch::query()->where('is_active', true);

        if ($branchId = $this->option('branch')) {
            $branchQuery->where('id', $branchId);
        }

        $branches = $branchQuery->pluck('id');

        if ($branches->isEmpty()) {
            $this->warn('No active branches found.');

            return self::SUCCESS;
        }

        $this->info("Computing smart categories for {$branches->count()} branch(es)...");

        foreach ($branches as $branchId) {
            $service->warmCacheForBranch($branchId);
            $this->line("  ✓ Branch #{$branchId}");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
