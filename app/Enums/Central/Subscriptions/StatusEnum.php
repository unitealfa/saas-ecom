<?php

namespace App\Enums\Central\Subscriptions;

enum StatusEnum: int
{
    case SCHEDULED = 1;
    case TRIAL = 2;
    case ACTIVE = 3;
    case EXPIRED = 4;
    case CANCELLED = 5;

    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => 'Prévu',
            self::TRIAL => 'Essai',
            self::ACTIVE => 'Actif',
            self::EXPIRED => 'Expiré',
            self::CANCELLED => 'Annulé',
        };
    }
}
