<?php

namespace App\Enums\Central\Billing;

enum DocumentTypeEnum: int
{
    case INVOICE = 1;
    case CREDIT_NOTE = 2;

    public function label(): string
    {
        return match ($this) {
            self::INVOICE => 'Facture',
            self::CREDIT_NOTE => 'Avoir',
        };
    }

    public function issuePermission(): string
    {
        return match ($this) {
            self::INVOICE => 'saas.invoices.issue',
            self::CREDIT_NOTE => 'saas.credit-notes.issue',
        };
    }

    public function numberingPrefix(): string
    {
        return match ($this) {
            self::INVOICE => 'FAC',
            self::CREDIT_NOTE => 'AVOIR',
        };
    }
}
