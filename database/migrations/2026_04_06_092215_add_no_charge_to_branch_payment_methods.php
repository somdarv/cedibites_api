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

        DB::statement('ALTER TABLE branch_payment_methods DROP CONSTRAINT IF EXISTS branch_payment_methods_payment_method_check');
        DB::statement("ALTER TABLE branch_payment_methods ADD CONSTRAINT branch_payment_methods_payment_method_check CHECK (payment_method::text = ANY (ARRAY['momo'::text, 'cash_on_delivery'::text, 'card'::text, 'bank_transfer'::text, 'no_charge'::text]))");
    }

    public function down(): void
    {
        DB::table('branch_payment_methods')->where('payment_method', 'no_charge')->delete();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE branch_payment_methods DROP CONSTRAINT IF EXISTS branch_payment_methods_payment_method_check');
        DB::statement("ALTER TABLE branch_payment_methods ADD CONSTRAINT branch_payment_methods_payment_method_check CHECK (payment_method::text = ANY (ARRAY['momo'::text, 'cash_on_delivery'::text, 'card'::text, 'bank_transfer'::text]))");
    }
};
