<?php

namespace App\Models\Central\Billing;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Billing\DocumentTypeEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $uuid
 * @property DocumentTypeEnum $document_type
 * @property int $fiscal_year
 * @property string $prefix
 * @property int $next_number
 */
#[Fillable([])]
class BillingSequence extends Model
{
    use CentralConnection, HasPublicUuid;

    public const int RECORD_TYPE = 1;

    protected $table = 'saas_billing_settings';

    protected $attributes = ['record_type' => self::RECORD_TYPE];

    protected static function booted(): void
    {
        static::addGlobalScope('billing_sequence', fn (Builder $query) => $query->where('record_type', self::RECORD_TYPE));
    }

    protected function casts(): array
    {
        return ['document_type' => DocumentTypeEnum::class, 'fiscal_year' => 'integer', 'next_number' => 'integer'];
    }

    public function getMorphClass(): string
    {
        return 'saas_sequence';
    }
}
