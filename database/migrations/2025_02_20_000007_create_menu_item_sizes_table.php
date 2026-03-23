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
        Schema::create('menu_item_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items')->onDelete('cascade');
            $table->string('option_key');
            $table->string('option_label');
            $table->decimal('price', 10, 2);
            $table->integer('display_order')->default(0)->index();
            $table->boolean('is_available')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('menu_item_id');
            $table->unique(['menu_item_id', 'option_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_item_options');
    }
};
