<?php

namespace App\Enums\Tenant\Users;

enum MembershipStatusEnum: int
{
    case ACTIVE = 1;
    case INVITED = 2;
    case SUSPENDED = 3;
    case REVOKED = 4;
}
