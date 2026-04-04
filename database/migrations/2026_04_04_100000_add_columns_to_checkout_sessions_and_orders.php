<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->timestamp('last_momo_sent_at')->nullable()->after('payment_gateway_response');
            $table->string('failure_reason')->nullable()->after('last_momo_sent_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('momo_number')->nullable()->after('contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('checkout_sessions', function (Blueprint $table) {
            $table->dropColumn(['last_momo_sent_at', 'failure_reason']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('momo_number');
        });
    }
};
