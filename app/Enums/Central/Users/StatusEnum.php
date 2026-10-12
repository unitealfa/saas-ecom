<?php

namespace App\Enums\Central\Users;

enum StatusEnum: int
{
    case ACTIVE = 1;
    case INACTIVE = 2;
    case SUSPENDED = 3;
    case DELETED = 4;
    case PENDING = 5;
    case REJECTED = 6;

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Actif',
            self::INACTIVE => 'Inactif',
            self::SUSPENDED => 'Suspendu',
            self::DELETED => 'Archivé',
            self::PENDING => 'En attente d’approbation',
            self::REJECTED => 'Refusé',
        };
    }
}
