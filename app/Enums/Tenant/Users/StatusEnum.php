<?php

namespace App\Enums\Tenant\Users;

enum StatusEnum: int
{
    case ACTIVE = 1;
    case INACTIVE = 2;
    case SUSPENDED = 3;
    case DELETED = 4;
}
