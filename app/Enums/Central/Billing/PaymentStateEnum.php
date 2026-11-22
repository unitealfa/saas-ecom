<?php

namespace App\Enums\Central\Billing;

enum PaymentStateEnum: string
{
    case DUE = 'due';
    case REVIEW = 'review';
    case PAID = 'paid';
    case ANOMALY = 'anomaly';
    case CREDITED = 'credited';

    case NO_PAYMENT_REQUIRED = 'no_payment_required';

    public function label(): string
    {
        return match ($this) {
            self::DUE => 'À payer',
            self::REVIEW => 'Paiement déclaré — à vérifier',
            self::PAID => 'Paiement intégral confirmé',
            self::ANOMALY => 'Paiement refusé ou anomalie',
            self::CREDITED => 'Dette intégralement corrigée par avoir',
            self::NO_PAYMENT_REQUIRED => 'Aucun paiement requis',
        };
    }
}
