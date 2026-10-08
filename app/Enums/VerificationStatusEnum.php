<?php

namespace App\Enums;

enum VerificationStatusEnum: int
{
    case PENDING = 1;
    case VERIFIED = 2;
    case FAILED = 3;
    case EXPIRED = 4;
    case INCOMPLETE = 5;
}
