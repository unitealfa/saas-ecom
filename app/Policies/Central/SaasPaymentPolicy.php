<?php

namespace App\Policies\Central;

use App\Enums\Central\Billing\TransferMethodEnum;
use App\Enums\Central\Billing\TransferStatusEnum;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;

class SaasPaymentPolicy
{
    public function __construct(private readonly CentralUserAccess $access) {}

    public function viewAny(User $actor): bool
    {
        return $this->has($actor, 'view');
    }

    public function viewOwn(User $actor): bool
    {
        return ! tenancy()->initialized && ! $this->access->isStaff($actor);
    }

    public function view(User $actor, SaasPayment $payment): bool
    {
        return $this->viewAny($actor) || $this->owns($actor, $payment);
    }

    public function verify(User $actor, SaasPayment $payment): bool
    {
        return $actor->id !== $payment->getAttribute('user_id') && $this->has($actor, 'validate');
    }

    public function reject(User $actor, SaasPayment $payment): bool
    {
        return $this->has($actor, 'reject') && $payment->getAttribute('transfer_status') === TransferStatusEnum::DECLARED
            && $payment->getAttribute('transfer_method') !== TransferMethodEnum::CASH_IN_PERSON;
    }

    public function reconcile(User $actor, SaasPayment $payment): bool
    {
        return $this->has($actor, 'reconcile')
            && ($payment->getAttribute('transfer_status') === TransferStatusEnum::UNCERTAIN
                || ($payment->getAttribute('transfer_status') === TransferStatusEnum::DECLARED
                    && $payment->getAttribute('transfer_method') !== TransferMethodEnum::CASH_IN_PERSON));
    }

    public function receipt(User $actor, SaasPayment $payment): bool
    {
        return $payment->getAttribute('transfer_status') === TransferStatusEnum::VERIFIED
            && $payment->getAttribute('reversal_of_id') === null && $this->view($actor, $payment);
    }

    public function supplyProof(User $actor, SaasPayment $payment): bool
    {
        return $this->owns($actor, $payment) && $payment->getAttribute('proof_media_id') === null
            && $payment->getAttribute('transfer_method') !== TransferMethodEnum::CASH_IN_PERSON
            && in_array($payment->getAttribute('transfer_status'), [TransferStatusEnum::DECLARED, TransferStatusEnum::UNCERTAIN], true);
    }

    private function owns(User $actor, SaasPayment $payment): bool
    {
        return ! tenancy()->initialized && ! $this->access->isStaff($actor) && $actor->id === $payment->getAttribute('user_id');
    }

    private function has(User $actor, string $action): bool
    {
        return ! tenancy()->initialized && $this->access->allows($actor, 'saas.payments.'.$action);
    }
}
