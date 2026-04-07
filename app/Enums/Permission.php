<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

enum Permission: string
{
    use HasEnumHelpers;

    // Order permissions
    case ViewOrders = 'view_orders';
    case CreateOrders = 'create_orders';
    case UpdateOrders = 'update_orders';
    case DeleteOrders = 'delete_orders';

    // Menu permissions
    case ViewMenu = 'view_menu';
    case ManageMenu = 'manage_menu';

    // Branch permissions
    case ViewBranches = 'view_branches';
    case ManageBranches = 'manage_branches';

    // Customer permissions
    case ViewCustomers = 'view_customers';
    case ManageCustomers = 'manage_customers';

    // Employee permissions
    case ViewEmployees = 'view_employees';
    case ManageEmployees = 'manage_employees';

    // Analytics permissions
    case ViewAnalytics = 'view_analytics';

    // Audit
    case ViewActivityLog = 'view_activity_log';

    // Portal access
    case AccessAdminPanel = 'access_admin_panel';
    case AccessManagerPortal = 'access_manager_portal';
    case AccessSalesPortal = 'access_sales_portal';
    case AccessPartnerPortal = 'access_partner_portal';
    case AccessPos = 'access_pos';
    case AccessKitchen = 'access_kitchen';
    case AccessOrderManager = 'access_order_manager';

    // Feature flags (nav gating within portals)
    case ManageShifts = 'manage_shifts';
    case ManageSettings = 'manage_settings';
    case ViewMyShifts = 'view_my_shifts';
    case ViewMySales = 'view_my_sales';

    // Platform Admin permissions
    case AccessPlatformAdmin = 'access_platform_admin';
    case ViewSystemHealth = 'view_system_health';
    case ViewErrorLogs = 'view_error_logs';
    case ManageRoles = 'manage_roles';
    case ResetPasswords = 'reset_passwords';
    case ManagePlatform = 'manage_platform';
    case ManageCache = 'manage_cache';
    case ToggleMaintenance = 'toggle_maintenance';
}
