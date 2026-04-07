<?php

use App\Enums\SmartCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_category_settings', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->unsignedSmallInteger('item_limit')->default(10);
            $table->unsignedSmallInteger('visible_hour_start')->nullable();
            $table->unsignedSmallInteger('visible_hour_end')->nullable();
            $table->timestamps();
        });

        // Seed default settings for all smart categories
        $order = 0;
        foreach (SmartCategory::cases() as $category) {
            $hours = $category->visibleHours();
            DB::table('smart_category_settings')->insert([
                'slug' => $category->value,
                'is_enabled' => true,
                'display_order' => $order++,
                'item_limit' => $category->defaultLimit(),
                'visible_hour_start' => $hours['start'] ?? null,
                'visible_hour_end' => $hours['end'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_category_settings');
    }
};
