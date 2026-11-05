<?php

namespace App\Enums\Central\Billing;

enum DocumentStatusEnum: int
{
    case DRAFT = 1;
    case ISSUED = 2;
    case CANCELLED = 3;
    case PREPARING = 4;

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::ISSUED => 'Émise',
            self::CANCELLED => 'Annulée',
            self::PREPARING => 'En préparation — numéro réservé',
        };
    }
}
