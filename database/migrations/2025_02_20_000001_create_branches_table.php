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
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('area');
            $table->string('address');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // Branch operating hours - normalized for flexibility
        Schema::create('branch_operating_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);
            $table->boolean('is_open')->default(true);
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            // Manual override - null means follow schedule, true/false overrides schedule for this day
            $table->boolean('manual_override_open')->nullable();
            // When the manual override was set (for automatic reset logic)
            $table->dateTime('manual_override_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'day_of_week']);
        });

        // Branch delivery settings - allows historical tracking and time-based changes
        Schema::create('branch_delivery_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->decimal('base_delivery_fee', 10, 2)->default(0.00);
            $table->decimal('per_km_fee', 10, 2)->default(0.00);
            $table->decimal('delivery_radius_km', 5, 2)->default(5.0);
            $table->decimal('min_order_value', 10, 2)->default(0.00);
            $table->string('estimated_delivery_time')->nullable(); // e.g., "30-45 mins"
            $table->dateTime('effective_from')->nullable();
            $table->dateTime('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'is_active', 'effective_from']);
        });

        // Branch order types - normalized for easy addition of new types
        Schema::create('branch_order_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->enum('order_type', ['delivery', 'pickup', 'dine_in'])->index();
            $table->boolean('is_enabled')->default(true);
            $table->json('metadata')->nullable(); // For type-specific settings like dine-in capacity
            $table->timestamps();

            $table->unique(['branch_id', 'order_type']);
        });

        // Branch payment methods - normalized for easy addition of new methods
        Schema::create('branch_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->onDelete('cascade');
            $table->enum('payment_method', ['momo', 'cash_on_delivery', 'cash_at_pickup', 'card', 'bank_transfer'])->index();
            $table->boolean('is_enabled')->default(true);
            $table->json('metadata')->nullable(); // For method-specific settings like momo provider
            $table->timestamps();

            $table->unique(['branch_id', 'payment_method']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_payment_methods');
        Schema::dropIfExists('branch_order_types');
        Schema::dropIfExists('branch_delivery_settings');
        Schema::dropIfExists('branch_operating_hours');
        Schema::dropIfExists('branches');
    }
};
