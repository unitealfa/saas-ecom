<?php

namespace App\Enums\Central\Subscriptions;

enum PaymentChoiceEnum: int
{
    case CASH = 1;
    case EXTERNAL = 2;

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Payer en espèces au bureau',
            self::EXTERNAL => 'Versement externe avec justificatif',
        };
    }
}
