<?php

namespace App\Services\Central\Subscriptions;

use App\Enums\Central\Billing\TransferMethodEnum;
use App\Enums\Central\Billing\TransferStatusEnum;
use App\Enums\Central\Subscriptions\PaymentChoiceEnum;
use App\Enums\Central\Subscriptions\RequestStatusEnum;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Subscriptions\SubscriptionRequest;
use App\Models\Central\Users\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Projection de la demande dans la transaction du paiement existant. */
class SubscriptionRequestPaymentState
{
    public const string SUBMITTED = 'subscription_request_payment_submitted';

    public const string ACCEPTED = 'subscription_request_accepted';

    public const string REJECTED = 'subscription_request_rejected';

    public const string SUPERSEDED = 'subscription_request_proof_superseded';

    public function assertInvoice(SaasInvoice $invoice): ?SubscriptionRequest
    {
        $id = $invoice->subscription->getAttribute('subscription_request_id');
        if ($id === null) {
            return null;
        }
        $request = SubscriptionRequest::whereKey($id)->lockForUpdate()->firstOrFail();
        if ($request->current_subscription_id !== $invoice->subscription_id || $request->current_invoice_id !== $invoice->id
            || $request->status === RequestStatusEnum::CANCELLED) {
            $this->fail('Cette facture appartient à une ancienne demande. Aucun nouveau paiement ne peut la régler.');
        }

        return $request;
    }

    public function assertDeclaration(User $actor, SaasInvoice $invoice, bool $cash, ?string $expected): void
    {
        $request = $this->assertInvoice($invoice);
        if ($request === null) {
            return;
        }
        if ($expected === null || ! hash_equals($request->managementState(), $expected)) {
            throw ValidationException::withMessages(['conflict' => 'La demande a changé. Utilisez sa dernière version pour déclarer ce paiement.']);
        }
        if ($request->status->isFinal() || $request->payment_choice !== ($cash ? PaymentChoiceEnum::CASH : PaymentChoiceEnum::EXTERNAL)) {
            $this->fail('La demande est terminée ou son moyen de paiement a changé. Rechargez sa dernière version.');
        }
        if ($request->current_payment_id !== null) {
            $payment = SaasPayment::whereKey($request->current_payment_id)->lockForUpdate()->firstOrFail();
            if (! $cash && ! $payment->hasReportedFunds() && in_array($payment->getAttribute('transfer_status'), [TransferStatusEnum::DECLARED, TransferStatusEnum::UNCERTAIN], true)) {
                if ($payment->getAttribute('transfer_status') === TransferStatusEnum::DECLARED) {
                    $payment->forceFill(['transfer_status' => TransferStatusEnum::CANCELLED, 'reason' => 'Justificatif remplacé par une nouvelle soumission.'])->save();
                }
                $this->audit($actor, $request, $payment, self::SUPERSEDED, 'Ancienne déclaration non confirmée remplacée, preuve conservée.');

                return;
            }
            if (! in_array($payment->getAttribute('transfer_status'), [TransferStatusEnum::REJECTED, TransferStatusEnum::CANCELLED], true)) {
                $this->fail('Une déclaration existe déjà. Reprenez-la ou corrigez la demande au lieu de créer un second encaissement.');
            }
        }
    }

    public function declared(User $actor, SaasInvoice $invoice, SaasPayment $payment): void
    {
        $request = $this->assertInvoice($invoice);
        if ($request === null) {
            return;
        }
        $request->forceFill(['current_payment_id' => $payment->getKey(), 'status' => RequestStatusEnum::REVIEW,
            'reference_version' => $request->reference_version + 1, 'decision_reason' => null, 'decided_at' => null, 'decided_by_id' => null])->save();
        $payment->forceFill(['encrypted_transfer_details' => [...($payment->getAttribute('encrypted_transfer_details') ?? []),
            'request_uuid' => $request->uuid, 'request_version' => $request->reference_version]])->save();
        $this->audit($actor, $request, $payment, self::SUBMITTED, 'Déclaration soumise pour la version courante, sans confirmation des fonds.');
    }

    public function assertCurrentPayment(SaasPayment $payment): ?SubscriptionRequest
    {
        $request = $this->assertInvoice($payment->invoice);
        if ($request === null) {
            return null;
        }
        $details = $payment->getAttribute('encrypted_transfer_details') ?? [];
        $cash = $payment->getAttribute('transfer_method') === TransferMethodEnum::CASH_IN_PERSON;
        if ($request->status->isFinal() || $request->current_payment_id !== $payment->getKey()
            || ($details['request_uuid'] ?? null) !== $request->uuid || ($details['request_version'] ?? null) !== $request->reference_version
            || $request->payment_choice !== ($cash ? PaymentChoiceEnum::CASH : PaymentChoiceEnum::EXTERNAL)) {
            $this->fail('La demande ou le justificatif a changé. Cette ancienne version ne peut pas être acceptée ou refusée.');
        }

        return $request;
    }

    public function verified(User $actor, SaasPayment $payment): void
    {
        $request = $this->assertCurrentPayment($payment);
        if ($request === null) {
            return;
        }
        Gate::forUser($actor)->authorize('accept', $request);
        if ($payment->getAttribute('transfer_status') !== TransferStatusEnum::VERIFIED) {
            $this->fail('Une demande ne peut être acceptée sans encaissement intégral vérifié.');
        }
        $request->forceFill(['status' => RequestStatusEnum::ACCEPTED, 'decided_by_id' => $actor->id, 'decided_at' => now(),
            'decision_reason' => null, 'reference_version' => $request->reference_version + 1])->save();
        $this->audit($actor, $request, $payment, self::ACCEPTED, 'Paiement intégral confirmé, demande acceptée et droits appliqués dans la même transaction.');
    }

    public function rejected(User $actor, SaasPayment $payment, string $reason): void
    {
        $request = $this->assertCurrentPayment($payment);
        if ($request === null) {
            return;
        }
        Gate::forUser($actor)->authorize('reject', $request);
        $request->forceFill(['status' => RequestStatusEnum::REJECTED, 'decided_by_id' => $actor->id, 'decided_at' => now(),
            'decision_reason' => $reason, 'reference_version' => $request->reference_version + 1])->save();
        $this->audit($actor, $request, $payment, self::REJECTED, 'Demande refusée avec motif, sans activation ni effacement d’encaissement.');
    }

    private function audit(User $actor, SubscriptionRequest $request, SaasPayment $payment, string $event, string $message): void
    {
        activity('central')->causedBy($actor)->performedOn($request)->event($event)
            ->withProperties(['request_uuid' => $request->uuid, 'version' => $request->reference_version,
                'payment_uuid' => $payment->getAttribute('uuid'), 'amount' => $payment->getAttribute('amount'),
                'currency' => $payment->getAttribute('currency'), 'reason' => $request->getAttribute('decision_reason')])->log($message);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['request' => $message]);
    }
}
