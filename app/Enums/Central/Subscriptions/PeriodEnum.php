<?php

namespace App\Enums\Central\Subscriptions;

enum PeriodEnum: int
{
    case MONTHLY = 1;
    case YEARLY = 2;

    public function label(): string
    {
        return match ($this) {
            self::MONTHLY => 'Mensuelle',
            self::YEARLY => 'Annuelle',
        };
    }
}
