<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_token')->unique();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('session_type', 10); // online, pos
            $table->string('status', 20)->default('pending'); // pending, payment_initiated, confirmed, failed, expired

            // Customer info
            $table->string('customer_name');
            $table->string('customer_phone', 20);
            $table->string('delivery_address')->nullable();
            $table->decimal('delivery_latitude', 10, 8)->nullable();
            $table->decimal('delivery_longitude', 11, 8)->nullable();
            $table->text('special_instructions')->nullable();

            // Order details
            $table->string('fulfillment_type', 20); // delivery, pickup, dine_in, takeaway
            $table->string('payment_method', 20); // mobile_money, cash, card, no_charge, manual_momo
            $table->string('momo_number', 20)->nullable();

            // Items snapshot
            $table->jsonb('items');

            // Totals
            $table->decimal('subtotal', 10, 2);
            $table->decimal('service_charge', 10, 2)->default(0);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);

            // Relationships
            $table->foreignId('staff_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('cart_id')->nullable()->constrained('carts')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // Payment gateway
            $table->string('hubtel_transaction_id')->nullable();
            $table->string('hubtel_checkout_url')->nullable();
            $table->jsonb('payment_gateway_response')->nullable();

            // Manual entry
            $table->boolean('is_manual_entry')->default(false);
            $table->timestamp('recorded_at')->nullable();
            $table->string('momo_reference', 100)->nullable();

            // Amount paid (for cash/card confirmation)
            $table->decimal('amount_paid', 10, 2)->nullable();

            // Expiry
            $table->timestamp('expires_at')->nullable()->index();

            // Resulting order (set after conversion)
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->timestamps();

            // Indexes
            $table->index(['staff_id', 'status']);
            $table->index(['expires_at', 'status']);
            $table->index('hubtel_transaction_id');
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_sessions');
    }
};
