<?php

namespace App\Enums\Central\Billing;

enum TransferStatusEnum: int
{
    case DECLARED = 1;
    case APPROVED = 2;
    case VERIFIED = 3;
    case REJECTED = 4;
    case CANCELLED = 5;
    case UNCERTAIN = 6;
    case REVERSED = 7;

    public function label(): string
    {
        return match ($this) {
            self::DECLARED => 'À vérifier', self::APPROVED => 'Contrôle en cours', self::VERIFIED => 'Payé',
            self::REJECTED => 'Refusé', self::CANCELLED => 'Annulé', self::UNCERTAIN => 'Anomalie à rapprocher',
            self::REVERSED => 'Correction comptable',
        };
    }
}
