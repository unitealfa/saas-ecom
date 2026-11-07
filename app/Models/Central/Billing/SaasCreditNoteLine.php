<?php

namespace App\Models\Central\Billing;

use App\Enums\Central\Billing\DocumentTypeEnum;
use Illuminate\Database\Eloquent\Builder;

class SaasCreditNoteLine extends SaasInvoiceLine
{
    protected static function booted(): void
    {
        static::addGlobalScope('credit_note', fn (Builder $query) => $query->where($query->qualifyColumn('document_type'), DocumentTypeEnum::CREDIT_NOTE));
    }
}
