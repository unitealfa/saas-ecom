<?php

namespace App\Enums;

enum ActivityOriginEnum: int
{
    case USER = 1;
    case SYSTEM = 2;
    case CARRIER = 3;
    case JOB = 4;
}
