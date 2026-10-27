<?php

namespace App\Enums\Central\Deployments;

enum StatusEnum: int
{
    case PENDING = 1;
    case RUNNING = 2;
    case SUCCEEDED = 3;
    case FAILED = 4;
    case CANCELLED = 5;

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente', self::RUNNING => 'En cours', self::SUCCEEDED => 'Réussi',
            self::FAILED => 'Échec', self::CANCELLED => 'Annulé',
        };
    }
}
