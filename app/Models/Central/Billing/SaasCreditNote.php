<?php

namespace App\Models\Central\Billing;

use App\Enums\Central\Billing\DocumentTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;

/** En-tête d'avoir dans la table partagée, sans exposer de nouvelle route du Module 20. */
#[Fillable([])]
class SaasCreditNote extends SaasInvoice
{
    protected $table = 'saas_invoices';

    protected $attributes = ['document_type' => DocumentTypeEnum::CREDIT_NOTE->value];

    protected static function booted(): void
    {
        static::addGlobalScope('credit_note', fn (Builder $query) => $query->where('document_type', DocumentTypeEnum::CREDIT_NOTE));
    }

    public function getMorphClass(): string
    {
        return 'saas_credit_note';
    }
}
