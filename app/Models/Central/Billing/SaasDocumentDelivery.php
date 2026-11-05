<?php

namespace App\Models\Central\Billing;

use App\Concerns\HasPublicUuid;
use App\Enums\Central\Billing\DeliveryChannelEnum;
use App\Enums\Central\Billing\DeliveryStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Fillable([])]
#[Hidden(['id', 'user_id', 'document_id', 'encrypted_recipient'])]
class SaasDocumentDelivery extends Model
{
    use CentralConnection, HasPublicUuid;

    protected $table = 'saas_document_deliveries';

    protected function casts(): array
    {
        return ['encrypted_recipient' => 'encrypted', 'channel' => DeliveryChannelEnum::class,
            'delivery_status' => DeliveryStatusEnum::class, 'delivery_attempts' => 'array',
            'attempts_count' => 'integer'];
    }

    public function getMorphClass(): string
    {
        return 'saas_document_delivery';
    }
}
