<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // boolean, string, integer, json
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Seed default settings
        DB::table('system_settings')->insert([
            [
                'key' => 'manual_entry_date_enabled',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Allow staff to select date (not just time) when creating manual entry orders',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'service_charge_percent',
                'value' => '1',
                'type' => 'integer',
                'description' => 'Service charge percentage applied to customer online orders',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
