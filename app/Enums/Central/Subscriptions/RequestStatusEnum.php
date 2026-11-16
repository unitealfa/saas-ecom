<?php

namespace App\Enums\Central\Subscriptions;

enum RequestStatusEnum: int
{
    case INVOICE_PENDING = 1;
    case AWAITING_PAYMENT = 2;
    case REVIEW = 3;
    case REJECTED = 4;
    case ACCEPTED = 5;
    case CANCELLED = 6;

    public function label(): string
    {
        return match ($this) {
            self::INVOICE_PENDING, self::AWAITING_PAYMENT => 'En attente de paiement',
            self::REVIEW => 'En vérification',
            self::REJECTED => 'Refusée',
            self::ACCEPTED => 'Acceptée',
            self::CANCELLED => 'Annulée',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::ACCEPTED, self::CANCELLED], true);
    }
}
