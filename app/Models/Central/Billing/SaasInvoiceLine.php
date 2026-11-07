<?php

namespace App\Models\Central\Billing;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Billing\DocumentTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $description
 * @property string $quantity
 * @property string $net_unit_price
 * @property string $net_discount
 * @property string $net_amount
 * @property string $tax_amount
 * @property string $total_amount
 */
#[Fillable([])]
class SaasInvoiceLine extends Model
{
    use CentralConnection, HasPublicUuid;

    protected $table = 'saas_invoice_lines';

    protected static function booted(): void
    {
        static::addGlobalScope('invoice', fn (Builder $query) => $query->where($query->qualifyColumn('document_type'), DocumentTypeEnum::INVOICE));
    }

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'net_unit_price' => 'decimal:2', 'net_discount' => 'decimal:2',
            'net_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'taxes' => 'array'];
    }

    /** @return BelongsTo<SaasInvoice, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo($this instanceof SaasCreditNoteLine ? SaasCreditNote::class : SaasInvoice::class, 'document_id');
    }
}
