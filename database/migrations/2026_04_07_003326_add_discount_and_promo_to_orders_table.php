<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('discount', 10, 2)->default(0)->after('service_charge');
            $table->unsignedBigInteger('promo_id')->nullable()->after('discount');
            $table->string('promo_name')->nullable()->after('promo_id');

            $table->foreign('promo_id')->references('id')->on('promos')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['promo_id']);
            $table->dropColumn(['discount', 'promo_id', 'promo_name']);
        });
    }
};
