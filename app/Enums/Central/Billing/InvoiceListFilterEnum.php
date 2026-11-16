<?php

namespace App\Enums\Central\Billing;

enum InvoiceListFilterEnum: string
{
    case ALL = 'all';
    case PAID = 'paid';
    case UNPAID = 'unpaid';
    case CORRECTED = 'corrected';
    case CANCELLED = 'cancelled';
    case PREPARING = 'preparing';

    public function label(): string
    {
        return match ($this) {
            self::ALL => 'Toutes les factures', self::PAID => 'Payées', self::UNPAID => 'Non payées',
            self::CORRECTED => 'Corrigées par avoir', self::CANCELLED => 'Brouillons annulés', self::PREPARING => 'En préparation',
        };
    }
}
