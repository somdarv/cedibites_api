<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_add_ons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('slug');
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->boolean('is_per_piece')->default(false);
            $table->unsignedInteger('display_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['branch_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_add_ons');
    }
};
