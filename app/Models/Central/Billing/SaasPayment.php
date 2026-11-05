<?php

namespace App\Models\Central\Billing;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Billing\TransferStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/** Lecture des paiements ; leur confirmation appartient au Module 21. */
#[Fillable([])]
class SaasPayment extends Model
{
    use CentralConnection, HasPublicUuid;

    public const int RECORD_TYPE = 1;

    protected $table = 'saas_transfers';

    protected static function booted(): void
    {
        static::addGlobalScope('payment', fn (Builder $query) => $query->where('record_type', self::RECORD_TYPE));
    }

    protected function casts(): array
    {
        return ['transfer_status' => TransferStatusEnum::class, 'amount' => 'decimal:2',
            'record_type' => 'integer', 'user_id' => 'integer', 'document_id' => 'integer',
            'reversal_of_id' => 'integer', 'occurred_at' => 'immutable_datetime', 'validated_at' => 'immutable_datetime'];
    }

    public function getMorphClass(): string
    {
        return 'saas_payment';
    }
}
