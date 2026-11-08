<?php

namespace App\Enums\Central\Billing;

enum DeliveryStatusEnum: int
{
    case PENDING = 1;
    case SENDING = 2;
    case SENT = 3;
    case DELIVERED = 4;
    case RETRYABLE = 5;
    case FAILED = 6;
    case UNCERTAIN = 7;
    case CANCELLED = 8;

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::SENDING => 'En cours',
            self::SENT => 'Accepté par le serveur e-mail',
            self::DELIVERED => 'Remis (preuve)',
            self::RETRYABLE => 'Échec technique — relance possible',
            self::FAILED => 'Échec définitif',
            self::UNCERTAIN => 'Résultat incertain — vérification manuelle',
            self::CANCELLED => 'Annulé',
        };
    }
}
