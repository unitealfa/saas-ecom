<?php

namespace App\Policies\Central;

use App\Enums\Central\Billing\DeliveryStatusEnum;
use App\Models\Central\Billing\SaasDocumentDelivery;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

class SaasDocumentDeliveryPolicy
{
    public function __construct(private readonly CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->has($actor, 'view');
    }

    public function view(User $actor, SaasDocumentDelivery $delivery): bool
    {
        return $this->viewAny($actor);
    }

    public function send(User $actor): bool
    {
        return $this->has($actor, 'send');
    }

    public function retry(User $actor, SaasDocumentDelivery $delivery): bool
    {
        return $this->has($actor, 'retry')
            && in_array($delivery->delivery_status, [DeliveryStatusEnum::FAILED, DeliveryStatusEnum::RETRYABLE], true)
            && $delivery->attempts_count < 3;
    }

    private function has(User $actor, string $permission): bool
    {
        return ! tenancy()->initialized && $this->access->allows($actor, 'saas.deliveries.'.$permission);
    }
}
