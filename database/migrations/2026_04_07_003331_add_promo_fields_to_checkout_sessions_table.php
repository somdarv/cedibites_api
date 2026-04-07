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
        Schema::table('checkout_sessions', function (Blueprint $table) {
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
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->dropForeign(['promo_id']);
            $table->dropColumn(['promo_id', 'promo_name']);
        });
    }
};
