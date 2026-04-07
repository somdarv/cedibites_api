<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rename platform_admin → tech_admin, merge super_admin → admin, delete super_admin.
     */
    public function up(): void
    {
        // 1. Rename platform_admin role to tech_admin
        DB::table('roles')
            ->where('name', 'platform_admin')
            ->where('guard_name', 'api')
            ->update(['name' => 'tech_admin']);

        // 2. Get role IDs
        $superAdminId = DB::table('roles')
            ->where('name', 'super_admin')
            ->where('guard_name', 'api')
            ->value('id');

        $adminId = DB::table('roles')
            ->where('name', 'admin')
            ->where('guard_name', 'api')
            ->value('id');

        if ($superAdminId && $adminId) {
            // 3. Move all super_admin users to admin role
            DB::table('model_has_roles')
                ->where('role_id', $superAdminId)
                ->update(['role_id' => $adminId]);

            // 4. Remove super_admin permission assignments
            DB::table('role_has_permissions')
                ->where('role_id', $superAdminId)
                ->delete();

            // 5. Delete the super_admin role
            DB::table('roles')
                ->where('id', $superAdminId)
                ->delete();
        }
    }

    /**
     * Reverse: rename tech_admin → platform_admin, recreate super_admin.
     */
    public function down(): void
    {
        // Rename tech_admin back to platform_admin
        DB::table('roles')
            ->where('name', 'tech_admin')
            ->where('guard_name', 'api')
            ->update(['name' => 'platform_admin']);

        // Recreate super_admin role (users will need manual re-assignment)
        DB::table('roles')->insert([
            'name' => 'super_admin',
            'guard_name' => 'api',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
