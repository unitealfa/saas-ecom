<?php

namespace App\Enums\Central\Features;

enum QuotaScopeEnum: int
{
    case OWNER = 1;
    case TENANT = 2;

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Par propriétaire', self::TENANT => 'Par boutique',
        };
    }
}
