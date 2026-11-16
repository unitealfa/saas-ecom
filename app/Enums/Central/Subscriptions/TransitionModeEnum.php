<?php

namespace App\Enums\Central\Subscriptions;

enum TransitionModeEnum: int
{
    case IMMEDIATE = 1;
    case DEFERRED = 2;

    public function label(): string
    {
        return match ($this) {
            self::IMMEDIATE => 'Dès confirmation du paiement',
            self::DEFERRED => 'À la fin de la période actuelle',
        };
    }
}
