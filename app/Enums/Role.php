<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

enum Role: string
{
    use HasEnumHelpers;

    case Admin = 'admin';
    case Manager = 'manager';
    case Employee = 'employee';
}
