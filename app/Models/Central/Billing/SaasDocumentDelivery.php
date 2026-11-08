<?php

namespace App\Models\Central\Billing;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Billing\DeliveryChannelEnum;
use App\Enums\Central\Billing\DeliveryStatusEnum;
use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Models\Central\Media\Media;
use App\Models\Central\Users\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Fillable([])]
#[Hidden(['id', 'user_id', 'document_id', 'created_by_id', 'proof_media_id', 'encrypted_recipient'])]
class SaasDocumentDelivery extends Model
{
    use CentralConnection, HasPublicUuid;

    protected $table = 'saas_document_deliveries';

    protected function casts(): array
    {
        return [
            'encrypted_recipient' => 'encrypted',
            'channel' => DeliveryChannelEnum::class,
            'document_type' => DocumentTypeEnum::class,
            'delivery_status' => DeliveryStatusEnum::class,
            'delivery_attempts' => 'array',
            'attempts_count' => 'integer',
            'next_attempt_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'sending_started_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<SaasInvoice, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(SaasInvoice::class, 'document_id')->withoutGlobalScope('invoice');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id')->withTrashed();
    }

    /** @return BelongsTo<Media, $this> */
    public function proof(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'proof_media_id');
    }

    public function managementState(): string
    {
        return hash('sha256', json_encode($this->only([
            'uuid', 'delivery_status', 'attempts_count', 'sent_at', 'delivered_at',
            'error_code', 'sending_started_at', 'updated_at',
        ]), JSON_THROW_ON_ERROR));
    }

    public function getMorphClass(): string
    {
        return 'saas_document_delivery';
    }
}
