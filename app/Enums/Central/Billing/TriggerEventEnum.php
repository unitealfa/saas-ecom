<?php

namespace App\Enums\Central\Billing;

enum TriggerEventEnum: string
{
    case INSTALLMENT_DUE = 'subscription.installment.due';

    public function label(): string
    {
        return 'Échéance d’abonnement à facturer';
    }
}
