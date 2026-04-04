<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Re-establish CHECK constraints on enum columns for orders and payments.
 *
 * Previous migrations that widened enums may have dropped constraints
 * without successfully re-creating them (due to differing LIKE patterns).
 * This migration idempotently ensures all constraints exist.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Orders table - 3 enum columns
        $this->ensureEnumConstraint('orders', 'order_source', [
            'online', 'phone', 'whatsapp', 'instagram', 'facebook', 'pos', 'manual_entry',
        ]);

        $this->ensureEnumConstraint('orders', 'order_type', [
            'delivery', 'pickup', 'dine_in', 'takeaway',
        ]);

        $this->ensureEnumConstraint('orders', 'status', [
            'received', 'accepted', 'preparing', 'ready',
            'out_for_delivery', 'delivered', 'ready_for_pickup',
            'completed', 'cancelled', 'cancel_requested',
        ]);

        // Payments table - 2 enum columns
        $this->ensureEnumConstraint('payments', 'payment_method', [
            'mobile_money', 'card', 'wallet', 'ghqr', 'cash', 'no_charge', 'manual_momo',
        ]);

        $this->ensureEnumConstraint('payments', 'payment_status', [
            'pending', 'completed', 'failed', 'refunded', 'cancelled', 'expired', 'no_charge',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: removing constraints would leave the DB less safe than before.
    }

    /**
     * Idempotently ensure a CHECK constraint exists for a varchar "enum" column.
     *
     * 1. Drop ALL existing check constraints that reference this column.
     * 2. Create a new constraint with the canonical name {table}_{column}_check.
     */
    private function ensureEnumConstraint(string $table, string $column, array $values): void
    {
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

        $valuesList = implode("', '", $values);
        $constraintName = "{$table}_{$column}_check";

        DB::statement(
            "ALTER TABLE \"{$table}\" ADD CONSTRAINT \"{$constraintName}\" CHECK ((\"{$column}\")::text = ANY (ARRAY['{$valuesList}']::text[]))"
        );
    }
};
