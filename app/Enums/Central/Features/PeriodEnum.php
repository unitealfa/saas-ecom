<?php

namespace App\Enums\Central\Features;

enum PeriodEnum: int
{
    case LIFETIME = 1;
    case DAILY = 2;
    case MONTHLY = 3;
    case YEARLY = 4;

    public function label(): string
    {
        return match ($this) {
            self::LIFETIME => 'Toute la durée',
            self::DAILY => 'Par jour',
            self::MONTHLY => 'Par mois',
            self::YEARLY => 'Par an',
        };
    }
}