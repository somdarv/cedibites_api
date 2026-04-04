<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->insertOrIgnore([
            [
                'key' => 'delivery_fee_enabled',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Whether to show and charge a delivery fee on customer orders',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'global_operating_hours_open',
                'value' => '08:00',
                'type' => 'string',
                'description' => 'Global opening time (HH:MM). Used as fallback when a branch has no hours configured.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'global_operating_hours_close',
                'value' => '22:00',
                'type' => 'string',
                'description' => 'Global closing time (HH:MM). Used as fallback when a branch has no hours configured.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', [
            'delivery_fee_enabled',
            'global_operating_hours_open',
            'global_operating_hours_close',
        ])->delete();
    }
};
