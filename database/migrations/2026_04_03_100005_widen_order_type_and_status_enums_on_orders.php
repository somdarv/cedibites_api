<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
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
     * Drop all check constraints on a column and add a new one with the given values.
     */
    private function replaceEnumConstraint(string $table, string $column, array $values): void
    {
        $constraints = DB::select("
            SELECT con.conname
            FROM pg_constraint con
            JOIN pg_class rel ON rel.oid = con.conrelid
            JOIN pg_namespace nsp ON nsp.oid = rel.relnamespace
            WHERE rel.relname = ?
              AND con.contype = 'c'
              AND pg_get_constraintdef(con.oid) LIKE ?
        ", [$table, "%{$column}%"]);

        foreach ($constraints as $con) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT \"{$con->conname}\"");
        }

        $valuesList = implode("', '", $values);
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_check CHECK ({$column}::text = ANY (ARRAY['{$valuesList}']::text[]))");
    }
};
