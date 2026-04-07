<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            [
                'key' => 'service_charge_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Whether the service charge is applied to online customer orders',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'service_charge_cap',
                'value' => '5',
                'type' => 'integer',
                'description' => 'Maximum service charge amount in GHS (cap). Set to 0 for no cap.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', [
            'service_charge_enabled',
            'service_charge_cap',
        ])->delete();
    }
};
