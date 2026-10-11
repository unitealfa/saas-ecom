<?php

namespace App\Enums\Central\Subscriptions;

enum StatusEnum: int
{
    case SCHEDULED = 1;
    case TRIAL = 2;
    case ACTIVE = 3;
    case EXPIRED = 4;
    case CANCELLED = 5;
}
