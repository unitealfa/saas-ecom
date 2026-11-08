<?php

namespace App\Models\Central\Billing;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Billing\TransferMethodEnum;
use App\Enums\Central\Billing\TransferStatusEnum;
use App\Models\Central\Media\Media;
use App\Models\Central\Users\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/** Paiement manuel central, distinct d’un remboursement. */
#[Fillable([])]
class SaasPayment extends Model
{
    use CentralConnection, HasPublicUuid;

    public const int RECORD_TYPE = 1;

    public const string STATE_PATTERN = '/^[a-f0-9]{64}$/D';

    protected $table = 'saas_transfers';

    protected static function booted(): void
    {
        static::addGlobalScope('payment', fn (Builder $query) => $query->where('record_type', self::RECORD_TYPE));
    }

    protected function casts(): array
    {
        return ['transfer_status' => TransferStatusEnum::class, 'transfer_method' => TransferMethodEnum::class,
            'encrypted_transfer_details' => 'encrypted:array', 'amount' => 'decimal:2',
            'record_type' => 'integer', 'user_id' => 'integer', 'document_id' => 'integer',
            'reversal_of_id' => 'integer', 'occurred_at' => 'immutable_datetime', 'validated_at' => 'immutable_datetime'];
    }

    public function getMorphClass(): string
    {
        return 'saas_payment';
    }

    /** @return BelongsTo<SaasInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SaasInvoice::class, 'document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id')->withTrashed();
    }

    public function statusLabel(): string
    {
        return $this->getAttribute('reversal_of_id') !== null ? 'Correction comptable' : $this->getAttribute('transfer_status')->label();
    }

    /** Un signal de réception bloque une correction commerciale, même sans validation finale. */
    public function hasReportedFunds(): bool
    {
        $details = $this->getAttribute('encrypted_transfer_details') ?? [];

        return $this->getAttribute('transfer_method') === TransferMethodEnum::CASH_IN_PERSON
            || in_array($this->getAttribute('transfer_status'), [TransferStatusEnum::VERIFIED, TransferStatusEnum::REVERSED], true)
            || ($details['cash_received'] ?? false) === true || isset($details['receipt_reported_by_id']);
    }

    /** @return MorphMany<Media, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Media::class, 'model');
    }

    public function managementState(): string
    {
        return hash('sha256', json_encode($this->getAttributes(), JSON_THROW_ON_ERROR));
    }
}
