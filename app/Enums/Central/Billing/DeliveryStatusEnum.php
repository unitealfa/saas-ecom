<?php

namespace App\Enums\Central\Billing;

enum DeliveryStatusEnum: int
{
    case PENDING = 1;
    case SENDING = 2;
    case SENT = 3;
    case DELIVERED = 4;
    case RETRYABLE = 5;
    case FAILED = 6;
    case UNCERTAIN = 7;
    case CANCELLED = 8;
}
