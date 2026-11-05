<?php

namespace App\Enums\Central\Billing;

enum TransferStatusEnum: int
{
    case DECLARED = 1;
    case APPROVED = 2;
    case VERIFIED = 3;
    case REJECTED = 4;
    case CANCELLED = 5;
    case UNCERTAIN = 6;
    case REVERSED = 7;
}
