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
}
