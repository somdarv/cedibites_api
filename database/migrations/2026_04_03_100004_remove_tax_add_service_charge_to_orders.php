<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('service_charge', 10, 2)->default(0)->after('delivery_fee');
        });

        // Migrate existing tax_amount data to service_charge (will be 0 going forward for POS)
        // and then drop the old tax columns
        \Illuminate\Support\Facades\DB::statement('UPDATE orders SET service_charge = 0');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'tax_amount']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 4)->default(0)->after('delivery_fee');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('service_charge');
        });
    }
};
