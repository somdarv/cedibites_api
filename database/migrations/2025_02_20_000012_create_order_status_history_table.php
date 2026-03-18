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
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->enum('status', ['received', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'ready_for_pickup', 'completed', 'cancelled']);
            $table->text('notes')->nullable();
            $table->enum('changed_by_type', ['customer', 'employee', 'system'])->default('system');
            $table->unsignedBigInteger('changed_by_id')->nullable();
            $table->dateTime('changed_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['order_id', 'changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
    }
};
