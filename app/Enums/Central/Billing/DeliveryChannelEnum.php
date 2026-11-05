<?php

namespace App\Enums\Central\Billing;

enum DeliveryChannelEnum: int
{
    case EMAIL = 1;
    case SMS = 2;
    case WHATSAPP = 3;
    case HANDOVER = 4;
}
