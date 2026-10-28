<?php

namespace App\Enums\Central\Features;

enum ValueTypeEnum: int
{
    case BOOLEAN = 1;
    case QUANTITY = 2;

    public function label(): string
    {
        return match ($this) {
            self::BOOLEAN => 'Oui / non',
            self::QUANTITY => 'Quantité',
        };
    }
}