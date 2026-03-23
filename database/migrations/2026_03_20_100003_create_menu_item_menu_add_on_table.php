<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_menu_add_on', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items')->onDelete('cascade');
            $table->foreignId('menu_add_on_id')->constrained('menu_add_ons')->onDelete('cascade');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['menu_item_id', 'menu_add_on_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_menu_add_on');
    }
};
