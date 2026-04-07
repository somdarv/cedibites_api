<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Migrate users from legacy 'employee' role to 'sales_staff',
     * then delete the 'employee' role from the database.
     */
    public function up(): void
    {
        $employeeRole = Role::where('name', 'employee')->where('guard_name', 'api')->first();
        $salesStaffRole = Role::where('name', 'sales_staff')->where('guard_name', 'api')->first();

        if ($employeeRole && $salesStaffRole) {
            // Re-assign all users from employee → sales_staff
            DB::table('model_has_roles')
                ->where('role_id', $employeeRole->id)
                ->update(['role_id' => $salesStaffRole->id]);

            // Remove role-permission assignments
            DB::table('role_has_permissions')
                ->where('role_id', $employeeRole->id)
                ->delete();

            // Delete the legacy role
            $employeeRole->delete();
        } elseif ($employeeRole) {
            // sales_staff doesn't exist yet — just delete the orphaned role
            DB::table('model_has_roles')
                ->where('role_id', $employeeRole->id)
                ->delete();
            DB::table('role_has_permissions')
                ->where('role_id', $employeeRole->id)
                ->delete();
            $employeeRole->delete();
        }
    }

    /**
     * Re-create the legacy employee role (no permission sync).
     */
    public function down(): void
    {
        Role::firstOrCreate(
            ['name' => 'employee', 'guard_name' => 'api'],
        );
    }
};
