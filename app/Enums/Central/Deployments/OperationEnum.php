<?php

namespace App\Enums\Central\Deployments;

enum OperationEnum: int
{
    case PROVISIONING = 1;
    case MIGRATION = 2;

    public function label(): string
    {
        return match ($this) {
            self::PROVISIONING => 'Provisionnement', self::MIGRATION => 'Migration',
        };
    }
}
