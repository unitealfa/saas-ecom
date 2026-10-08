<?php

namespace App\Enums\Central\Tenants;

enum StatusEnum: int
{
    case PROVISIONING = 1;
    case ACTIVE = 2;
    case INACTIVE = 3;
    case SUSPENDED = 4;
    case OVER_QUOTA = 5;
    case PROVISIONING_FAILED = 6;
    case DELETED = 9;
}
