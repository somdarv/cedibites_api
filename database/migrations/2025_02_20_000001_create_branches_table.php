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
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->boolean('is_active')->default(true)->index();
            $table->string('operating_hours')->nullable();
            $table->decimal('delivery_fee', 10, 2)->default(0.00);
            $table->decimal('delivery_radius_km', 5, 2)->default(5.0);
            $table->string('estimated_delivery_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
