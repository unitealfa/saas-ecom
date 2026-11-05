<?php

namespace App\Enums\Central\Subscriptions;

enum InstallmentStatusEnum: int
{
    case PENDING = 1;
    case PARTIALLY_PAID = 2;
    case PAID = 3;
    case CANCELLED = 4;

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente de règlement',
            self::PARTIALLY_PAID => 'Partiellement réglée',
            self::PAID => 'Réglée',
            self::CANCELLED => 'Annulée',
        };
    }
}
