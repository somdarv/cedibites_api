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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 20)->unique()->index();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('restrict');
            $table->foreignId('assigned_employee_id')->nullable()->constrained('employees')->onDelete('set null');
            $table->enum('order_type', ['delivery', 'pickup']);
            $table->enum('order_source', ['online', 'phone', 'whatsapp', 'instagram', 'facebook', 'pos']);
            $table->string('delivery_address')->nullable();
            $table->decimal('delivery_latitude', 10, 8)->nullable();
            $table->decimal('delivery_longitude', 11, 8)->nullable();
            $table->string('contact_name');
            $table->string('contact_phone');
            $table->text('delivery_note')->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0.00);
            $table->decimal('tax_rate', 5, 4)->default(0.0250);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['received', 'accepted', 'preparing', 'ready', 'out_for_delivery', 'delivered', 'ready_for_pickup', 'completed', 'cancelled'])->default('received')->index();
            $table->integer('estimated_prep_time')->nullable();
            $table->dateTime('estimated_delivery_time')->nullable();
            $table->dateTime('actual_delivery_time')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'created_at']);
            $table->index(['branch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
