<?php

namespace App\Enums\Central\Features;

enum QuotaScopeEnum: int
{
    case OWNER = 1;
    case TENANT = 2;
}
