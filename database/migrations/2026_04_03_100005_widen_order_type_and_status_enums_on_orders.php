<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // 1. Widen order_type to include dine_in and takeaway
        $this->replaceEnumConstraint(
            'orders',
            'order_type',
            ['delivery', 'pickup', 'dine_in', 'takeaway']
        );

        // 2. Widen status to include cancel_requested
        $this->replaceEnumConstraint(
            'orders',
            'status',
            ['received', 'accepted', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'ready_for_pickup', 'completed', 'cancelled', 'cancel_requested']
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $this->replaceEnumConstraint(
            'orders',
            'order_type',
            ['delivery', 'pickup']
        );

        $this->replaceEnumConstraint(
            'orders',
            'status',
            ['received', 'accepted', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'ready_for_pickup', 'completed', 'cancelled']
        );
    }

    /**
     * Drop check constraints for a specific column and add a new one with given values.
     * Uses exact column name matching to avoid false positives.
     */
    private function replaceEnumConstraint(string $table, string $column, array $values): void
    {
        // Find constraints whose definition references this exact column
        // Use word-boundary matching: (column_name) to be precise
        $constraints = DB::select("
            SELECT con.conname
            FROM pg_constraint con
            JOIN pg_class rel ON rel.oid = con.conrelid
            JOIN pg_namespace nsp ON nsp.oid = rel.relnamespace
            WHERE rel.relname = ?
              AND con.contype = 'c'
              AND pg_get_constraintdef(con.oid) LIKE ?
        ", [$table, "%({$column})%"]);

        foreach ($constraints as $con) {
            DB::statement("ALTER TABLE \"{$table}\" DROP CONSTRAINT \"{$con->conname}\"");
        }

        // Add new constraint
        $valuesList = implode("', '", $values);
        DB::statement("ALTER TABLE \"{$table}\" ADD CONSTRAINT \"{$table}_{$column}_check\" CHECK ((\"{$column}\")::text = ANY (ARRAY['{$valuesList}']::text[]))");
    }
};
