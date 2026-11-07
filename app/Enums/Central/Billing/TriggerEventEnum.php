<?php

namespace App\Enums\Central\Billing;

enum TriggerEventEnum: string
{
    case INSTALLMENT_DUE = 'subscription.installment.due';

    case INVOICE_CORRECTION = 'invoice.correction.requested';

    public function label(): string
    {
        return match ($this) {
            self::INSTALLMENT_DUE => 'Échéance d’abonnement à facturer',
            self::INVOICE_CORRECTION => 'Correction d’une facture émise',
        };
    }
}
