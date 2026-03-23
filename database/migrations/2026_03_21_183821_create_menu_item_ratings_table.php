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
        Schema::create('menu_item_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('menu_item_id')->constrained('menu_items')->onDelete('cascade');
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->onDelete('set null');
            $table->unsignedTinyInteger('rating');
            $table->timestamps();
            $table->softDeletes();
            $table->index('menu_item_id');
            $table->unique(['customer_id', 'menu_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_item_ratings');
    }
};
