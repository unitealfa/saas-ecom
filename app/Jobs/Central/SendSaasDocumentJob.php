<?php

namespace App\Jobs\Central;

use App\Services\Central\Billing\SaasDocumentDeliveryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSaasDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(SaasDocumentDeliveryService $service): void
    {
        $service->process($this->deliveryId);
    }
}
