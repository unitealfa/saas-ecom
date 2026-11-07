<?php

namespace App\Models\Central\Billing;

use App\Enums\Central\Billing\DocumentTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/** Avoir fiscal, distinct de sa facture originale conservée. */
#[Fillable([])]
class SaasCreditNote extends SaasInvoice
{
    public const string STATE_PATTERN = '/^[a-f0-9]{64}$/D';

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

    /** @return BelongsTo<SaasInvoice, $this> */
    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(SaasInvoice::class, 'original_invoice_id');
    }

    public function managementState(): string
    {
        $lines = $this->lines()->when(DB::transactionLevel() > 0, fn (Builder $query) => $query->lockForUpdate())->get();

        return hash('sha256', json_encode([$this->getAttributes(), $lines->map(fn (SaasInvoiceLine $line): array => $line->only([
            'uuid', 'original_invoice_line_id', 'quantity', 'net_amount', 'taxes', 'tax_amount', 'total_amount', 'reason',
        ]))->all()], JSON_THROW_ON_ERROR));
    }
}
