<?php

namespace App\Enums\Central\Features;

enum PeriodEnum: int
{
    case LIFETIME = 1;
    case DAILY = 2;
    case MONTHLY = 3;
    case YEARLY = 4;
}
