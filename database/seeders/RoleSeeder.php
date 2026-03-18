<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Create Super Admin role with all permissions (highest level)
        $superAdmin = Role::updateOrCreate(
            ['name' => RoleEnum::SuperAdmin->value, 'guard_name' => 'api'],
            ['name' => RoleEnum::SuperAdmin->value, 'guard_name' => 'api']
        );
        $superAdmin->syncPermissions(
            array_map(fn ($permission) => $permission->value, Permission::cases())
        );

        // Create Admin role with all permissions (legacy compatibility)
        $admin = Role::updateOrCreate(
            ['name' => RoleEnum::Admin->value, 'guard_name' => 'api'],
            ['name' => RoleEnum::Admin->value, 'guard_name' => 'api']
        );
        $admin->syncPermissions(
            array_map(fn ($permission) => $permission->value, Permission::cases())
        );

        // Create Branch Partner role (read-only investor access)
        $branchPartner = Role::updateOrCreate(
            ['name' => RoleEnum::BranchPartner->value, 'guard_name' => 'api'],
            ['name' => RoleEnum::BranchPartner->value, 'guard_name' => 'api']
        );
        $branchPartner->syncPermissions([
            Permission::ViewOrders->value,
            Permission::ViewMenu->value,
            Permission::ViewBranches->value,
            Permission::ViewCustomers->value,
            Permission::ViewEmployees->value,
            Permission::ViewAnalytics->value,
        ]);

        // Create Manager role (branch operations)
        $manager = Role::updateOrCreate(
            ['name' => RoleEnum::Manager->value, 'guard_name' => 'api'],
            ['name' => RoleEnum::Manager->value, 'guard_name' => 'api']
        );
        $manager->syncPermissions([
            Permission::ViewOrders->value,
            Permission::CreateOrders->value,
            Permission::UpdateOrders->value,
            Permission::DeleteOrders->value,
            Permission::ViewMenu->value,
            Permission::ManageMenu->value,
            Permission::ViewBranches->value,
            Permission::ManageBranches->value,
            Permission::ViewCustomers->value,
            Permission::ManageCustomers->value,
            Permission::ViewEmployees->value,
            Permission::ManageEmployees->value,
            Permission::ViewAnalytics->value,
        ]);

        // Create Call Center role (order placement)
        $callCenter = Role::updateOrCreate(
            ['name' => RoleEnum::CallCenter->value, 'guard_name' => 'api'],
            ['name' => RoleEnum::CallCenter->value, 'guard_name' => 'api']
        );
        $callCenter->syncPermissions([
            Permission::ViewOrders->value,
            Permission::CreateOrders->value,
            Permission::UpdateOrders->value,
            Permission::ViewMenu->value,
            Permission::ViewBranches->value,
            Permission::ViewCustomers->value,
            Permission::ManageCustomers->value,
        ]);

        // Create Kitchen role (kitchen display system)
        $kitchen = Role::updateOrCreate(
            ['name' => RoleEnum::Kitchen->value, 'guard_name' => 'api'],
            ['name' => RoleEnum::Kitchen->value, 'guard_name' => 'api']
        );
        $kitchen->syncPermissions([
            Permission::ViewOrders->value,
            Permission::UpdateOrders->value, // For order status updates
            Permission::ViewMenu->value,
        ]);

        // Create Rider role (delivery)
        $rider = Role::updateOrCreate(
            ['name' => RoleEnum::Rider->value, 'guard_name' => 'api'],
            ['name' => RoleEnum::Rider->value, 'guard_name' => 'api']
        );
        $rider->syncPermissions([
            Permission::ViewOrders->value,
            Permission::UpdateOrders->value, // For delivery status updates
            Permission::ViewCustomers->value,
        ]);

        // Create Employee role (legacy compatibility)
        $employee = Role::updateOrCreate(
            ['name' => RoleEnum::Employee->value, 'guard_name' => 'api'],
            ['name' => RoleEnum::Employee->value, 'guard_name' => 'api']
        );
        $employee->syncPermissions([
            Permission::ViewOrders->value,
            Permission::CreateOrders->value,
            Permission::UpdateOrders->value,
            Permission::ViewMenu->value,
            Permission::ViewBranches->value,
            Permission::ViewCustomers->value,
        ]);
    }
}
