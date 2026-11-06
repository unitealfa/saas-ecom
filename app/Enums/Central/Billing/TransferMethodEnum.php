<?php

namespace App\Enums\Central\Billing;

enum TransferMethodEnum: int
{
    case BANK_TRANSFER = 1;
    case CCP_TRANSFER = 2;
    case BARIDIMOB = 3;
    case OTHER_MANUAL = 4;
    case CASH_IN_PERSON = 5;

    public function label(): string
    {
        return match ($this) {
            self::BANK_TRANSFER => 'Virement bancaire', self::CCP_TRANSFER => 'Versement CCP',
            self::BARIDIMOB => 'BaridiMob', self::OTHER_MANUAL => 'Autre méthode historique',
            self::CASH_IN_PERSON => 'Espèces sur place',
        };
    }

    public function network(): string
    {
        return match ($this) {
            self::CCP_TRANSFER, self::BARIDIMOB => 'ccp',
            self::BANK_TRANSFER => 'bank', self::CASH_IN_PERSON => 'cash', self::OTHER_MANUAL => 'manual',
        };
    }
}
