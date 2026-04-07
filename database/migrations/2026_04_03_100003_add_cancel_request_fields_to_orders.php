<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add cancel_requested to the order status values
        // PostgreSQL requires ALTER TYPE to add enum values, but since Laravel
        // uses string columns (not native enums), we just add the new columns.
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('cancel_requested_by')->nullable()->after('cancelled_reason')->constrained('users')->nullOnDelete();
            $table->text('cancel_request_reason')->nullable()->after('cancel_requested_by');
            $table->timestamp('cancel_requested_at')->nullable()->after('cancel_request_reason');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['cancel_requested_by']);
            $table->dropColumn(['cancel_requested_by', 'cancel_request_reason', 'cancel_requested_at']);
        });
    }
};
