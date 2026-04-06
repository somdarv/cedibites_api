<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Delete any existing cash_at_pickup rows
        DB::table('branch_payment_methods')
            ->where('payment_method', 'cash_at_pickup')
            ->delete();

        // PostgreSQL: drop the old CHECK constraint and add a new one without cash_at_pickup
        DB::statement('ALTER TABLE branch_payment_methods DROP CONSTRAINT IF EXISTS branch_payment_methods_payment_method_check');
        DB::statement("ALTER TABLE branch_payment_methods ADD CONSTRAINT branch_payment_methods_payment_method_check CHECK (payment_method::text = ANY (ARRAY['momo'::text, 'cash_on_delivery'::text, 'card'::text, 'bank_transfer'::text]))");
    }

    public function down(): void
    {
        // Re-add cash_at_pickup to the CHECK constraint
        DB::statement('ALTER TABLE branch_payment_methods DROP CONSTRAINT IF EXISTS branch_payment_methods_payment_method_check');
        DB::statement("ALTER TABLE branch_payment_methods ADD CONSTRAINT branch_payment_methods_payment_method_check CHECK (payment_method::text = ANY (ARRAY['momo'::text, 'cash_on_delivery'::text, 'cash_at_pickup'::text, 'card'::text, 'bank_transfer'::text]))");
    }
};
