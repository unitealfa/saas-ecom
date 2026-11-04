<?php

namespace App\Enums\Central\Billing;

enum PolicyStatusEnum: int
{
    case DRAFT = 1;
    case VALIDATED = 2;
    case ACTIVE = 3;
    case RETIRED = 4;

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::VALIDATED => 'Validée',
            self::ACTIVE => 'Active',
            self::RETIRED => 'Retirée',
        };
    }
}
