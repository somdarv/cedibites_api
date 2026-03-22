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
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('menu_categories')->onDelete('set null');
            $table->string('name');
            $table->string('slug')->index();
            $table->text('description')->nullable();
            $table->boolean('is_available')->default(true)->index();
            $table->decimal('rating', 3, 1)->nullable()->after('is_available');
            $table->unsignedInteger('rating_count')->default(0)->after('rating');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['branch_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
