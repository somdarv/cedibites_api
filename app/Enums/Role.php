<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

enum Role: string
{
    use HasEnumHelpers;

    case Admin = 'admin';
    case Manager = 'manager';
    case SalesStaff = 'sales_staff';
    case SuperAdmin = 'super_admin';
    case BranchPartner = 'branch_partner';
    case CallCenter = 'call_center';
    case Kitchen = 'kitchen';
    case Rider = 'rider';
}
