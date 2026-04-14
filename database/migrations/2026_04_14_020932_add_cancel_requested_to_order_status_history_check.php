<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add 'cancel_requested' to the order_status_history.status CHECK constraint.
 *
 * The original create migration omitted this value, causing a CHECK violation
 * whenever the OrderObserver records a cancel_requested status change.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $table = 'order_status_history';
        $column = 'status';

        // Drop all existing check constraints that reference the status column
        $constraints = DB::select("
            SELECT con.conname
            FROM pg_constraint con
            JOIN pg_class rel ON rel.oid = con.conrelid
            JOIN pg_namespace nsp ON nsp.oid = rel.relnamespace
            WHERE rel.relname = ?
              AND nsp.nspname = 'public'
              AND con.contype = 'c'
              AND pg_get_constraintdef(con.oid) LIKE ?
        ", [$table, "%{$column}%"]);

        foreach ($constraints as $con) {
            DB::statement("ALTER TABLE \"{$table}\" DROP CONSTRAINT \"{$con->conname}\"");
        }

        // Re-create with the full set of valid statuses (including cancel_requested)
        $values = [
            'received', 'accepted', 'preparing', 'ready',
            'out_for_delivery', 'delivered', 'ready_for_pickup',
            'completed', 'cancelled', 'cancel_requested',
        ];

        $valuesList = implode("', '", $values);

        DB::statement(
            "ALTER TABLE \"{$table}\" ADD CONSTRAINT \"{$table}_{$column}_check\" CHECK ((\"{$column}\")::text = ANY (ARRAY['{$valuesList}']::text[]))"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: removing cancel_requested would break existing data.
    }
};
