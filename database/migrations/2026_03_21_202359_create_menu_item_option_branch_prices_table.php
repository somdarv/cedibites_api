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
        Schema::create('menu_item_option_branch_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_option_id')->constrained('menu_item_options')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('is_available')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['menu_item_option_id', 'branch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_item_option_branch_prices');
    }
};
