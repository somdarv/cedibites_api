<?php

namespace App\Enums;

use App\Enums\Concerns\HasEnumHelpers;

enum CustomerStatus: string
{
    use HasEnumHelpers;

    case Active = 'active';
    case Suspended = 'suspended';
}
