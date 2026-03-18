<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Get all roles with their permissions.
     */
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $this->formatRoleDisplayName($role->name),
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ];
        });

        return response()->success($roles, 'Roles retrieved successfully.');
    }

    /**
     * Get all permissions.
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::all()->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'display_name' => $this->formatPermissionDisplayName($permission->name),
                'description' => $this->getPermissionDescription($permission->name),
            ];
        });

        return response()->success($permissions, 'Permissions retrieved successfully.');
    }

    /**
     * Format role name for display.
     */
    private function formatRoleDisplayName(string $roleName): string
    {
        $displayNames = [
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'branch_partner' => 'Branch Partner',
            'manager' => 'Branch Manager',
            'call_center' => 'Call Center',
            'kitchen' => 'Kitchen Staff',
            'rider' => 'Delivery Rider',
            'employee' => 'Employee',
        ];

        return $displayNames[$roleName] ?? ucwords(str_replace('_', ' ', $roleName));
    }

    /**
     * Format permission name for display.
     */
    private function formatPermissionDisplayName(string $permissionName): string
    {
        $displayNames = [
            'view_orders' => 'Can Place Orders',
            'create_orders' => 'Can Place Orders',
            'update_orders' => 'Can Advance Orders',
            'delete_orders' => 'Can Cancel Orders',
            'view_menu' => 'Can View Menu',
            'manage_menu' => 'Can Manage Menu',
            'view_branches' => 'Can View Branches',
            'manage_branches' => 'Can Manage Branches',
            'view_customers' => 'Can View Customers',
            'manage_customers' => 'Can Manage Customers',
            'view_employees' => 'Can View Staff',
            'manage_employees' => 'Can Manage Staff',
            'view_analytics' => 'Can View Reports',
            'view_activity_log' => 'Can View Activity Log',
        ];

        return $displayNames[$permissionName] ?? ucwords(str_replace('_', ' ', $permissionName));
    }

    /**
     * Get permission description.
     */
    private function getPermissionDescription(string $permissionName): string
    {
        $descriptions = [
            'view_orders' => 'View orders and order history',
            'create_orders' => 'Create new orders via call center or manager portal',
            'update_orders' => 'Move orders through statuses (accept, start, complete)',
            'delete_orders' => 'Cancel or delete orders',
            'view_menu' => 'View menu items and categories',
            'manage_menu' => 'Add, edit, or remove menu items and categories',
            'view_branches' => 'View branch information',
            'manage_branches' => 'Create, edit, or manage branch settings',
            'view_customers' => 'View customer information',
            'manage_customers' => 'Create, edit, or manage customer accounts',
            'view_employees' => 'View staff accounts and information',
            'manage_employees' => 'Create, edit, or suspend staff accounts',
            'view_analytics' => 'Access to sales analytics and branch performance',
            'view_activity_log' => 'View system activity and audit logs',
        ];

        return $descriptions[$permissionName] ?? 'Permission to '.str_replace('_', ' ', $permissionName);
    }
}
