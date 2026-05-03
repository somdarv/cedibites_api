<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isPgsql = DB::connection()->getDriverName() === 'pgsql';

        // 1. Widen order_source enum to include 'manual_entry'
        //    Laravel enums on PG are varchar + check constraint.
        //    Drop existing constraint and recreate with new value.
        //    On non-pgsql (e.g. SQLite test DB) the column is plain TEXT with
        //    no check constraint to alter, so we skip the SQL.
        if ($isPgsql) {
            $this->replaceEnumConstraint(
                'orders',
                'order_source',
                ['online', 'phone', 'whatsapp', 'instagram', 'facebook', 'pos', 'manual_entry']
            );

            // 2. Widen payment_method enum to include 'manual_momo'
            $this->replaceEnumConstraint(
                'payments',
                'payment_method',
                ['mobile_money', 'card', 'wallet', 'ghqr', 'cash', 'no_charge', 'manual_momo']
            );
        }

        // 3. Add recorded_at — the actual date/time the paper order occurred
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('recorded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('recorded_at');
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $this->replaceEnumConstraint(
            'orders',
            'order_source',
            ['online', 'phone', 'whatsapp', 'instagram', 'facebook', 'pos']
        );

        $this->replaceEnumConstraint(
            'payments',
            'payment_method',
            ['mobile_money', 'card', 'wallet', 'ghqr', 'cash', 'no_charge']
        );
    }

    /**
     * Drop all check constraints on a column and add a new one with the given values.
     */
    private function replaceEnumConstraint(string $table, string $column, array $values): void
    {
        // Find and drop existing check constraints for this column
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

        // Add new constraint
        $valuesList = implode("', '", $values);
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_check CHECK ({$column}::text = ANY (ARRAY['{$valuesList}']::text[]))");
    }
};
