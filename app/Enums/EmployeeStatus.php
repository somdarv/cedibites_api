<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

enum EmployeeStatus: string
{
    use HasEnumHelpers;

    case Active = 'active';
    case OnLeave = 'on_leave';
    case Suspended = 'suspended';
    case Terminated = 'terminated';
}
