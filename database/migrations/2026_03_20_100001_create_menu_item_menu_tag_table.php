<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_item_menu_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained('menu_items')->onDelete('cascade');
            $table->foreignId('menu_tag_id')->constrained('menu_tags')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['menu_item_id', 'menu_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_item_menu_tag');
    }
};
