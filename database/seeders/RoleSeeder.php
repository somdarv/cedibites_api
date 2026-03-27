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
            Permission::AccessPartnerPortal->value,
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
            Permission::AccessManagerPortal->value,
            Permission::AccessPos->value,
            Permission::AccessKitchen->value,
            Permission::AccessOrderManager->value,
            Permission::ManageShifts->value,
            Permission::ManageSettings->value,
            Permission::ViewMyShifts->value,
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
            Permission::AccessSalesPortal->value,
            Permission::ViewMySales->value,
            Permission::ViewMyShifts->value,
        ]);

        // Create Kitchen role (kitchen display system)
        $kitchen = Role::updateOrCreate(
            ['name' => RoleEnum::Kitchen->value, 'guard_name' => 'api'],
            ['name' => RoleEnum::Kitchen->value, 'guard_name' => 'api']
        );
        $kitchen->syncPermissions([
            Permission::ViewOrders->value,
            Permission::UpdateOrders->value,
            Permission::ViewMenu->value,
            Permission::AccessKitchen->value,
        ]);

        // Create Rider role (delivery)
        $rider = Role::updateOrCreate(
            ['name' => RoleEnum::Rider->value, 'guard_name' => 'api'],
            ['name' => RoleEnum::Rider->value, 'guard_name' => 'api']
        );
        $rider->syncPermissions([
            Permission::ViewOrders->value,
            Permission::UpdateOrders->value,
            Permission::ViewCustomers->value,
            Permission::AccessOrderManager->value,
        ]);

        // Create Sales Staff role (replaces legacy "employee")
        $salesStaff = Role::updateOrCreate(
            ['name' => RoleEnum::SalesStaff->value, 'guard_name' => 'api'],
            ['name' => RoleEnum::SalesStaff->value, 'guard_name' => 'api']
        );
        $salesStaff->syncPermissions([
            Permission::ViewOrders->value,
            Permission::CreateOrders->value,
            Permission::UpdateOrders->value,
            Permission::ViewMenu->value,
            Permission::ViewBranches->value,
            Permission::ViewCustomers->value,
            Permission::AccessSalesPortal->value,
            Permission::AccessPos->value,
            Permission::ViewMySales->value,
            Permission::ViewMyShifts->value,
        ]);

        // Keep legacy "employee" role aligned for backward compatibility.
        $legacyEmployee = Role::where('name', RoleEnum::Employee->value)
            ->where('guard_name', 'api')
            ->first();
        if ($legacyEmployee instanceof Role) {
            $legacyEmployee->syncPermissions($salesStaff->permissions->pluck('name')->all());
        }
    }
}
