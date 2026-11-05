<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\PaymentStateEnum;
use App\Enums\Central\Billing\TransferStatusEnum;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Users\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class InvoicePaymentService
{
    /** @param Collection<int, SaasInvoice> $invoices
     * @return array<int, PaymentStateEnum>
     */
    public function states(User $actor, Collection $invoices): array
    {
        abort_if(tenancy()->initialized, 403);
        foreach ($invoices as $invoice) {
            $this->authorize($actor, $invoice);
        }
        $transfers = SaasPayment::withoutGlobalScope('payment')->whereIn('document_id', $invoices->pluck('id'))->get()->groupBy('document_id');

        return $invoices->mapWithKeys(fn (SaasInvoice $invoice): array => [
            $invoice->id => $this->resolve($invoice, $transfers->get($invoice->id, collect()))[0],
        ])->all();
    }

    /** Point de lecture pour un futur reçu, sans créer ni confirmer un paiement.
     */
    public function confirmedPayment(User $actor, SaasInvoice $invoice): ?SaasPayment
    {
        abort_if(tenancy()->initialized, 403);
        $current = SaasInvoice::whereKey($invoice->id)->firstOrFail();
        $this->authorize($actor, $current);
        $transfers = SaasPayment::withoutGlobalScope('payment')->where('document_id', $current->id)->get();

        return $this->resolve($current, $transfers)[1];
    }

    private function authorize(User $actor, SaasInvoice $invoice): void
    {
        if (! Gate::forUser($actor)->allows('view', $invoice)) {
            Gate::forUser($actor)->authorize('viewOwnInvoice', $invoice);
        }
    }

    /** @param Collection<int, SaasPayment> $transfers
     * @return array{PaymentStateEnum, ?SaasPayment}
     */
    private function resolve(SaasInvoice $invoice, Collection $transfers): array
    {
        $verified = $transfers->filter(fn (SaasPayment $payment): bool => $payment->getAttribute('transfer_status') === TransferStatusEnum::VERIFIED);
        if ($verified->isNotEmpty()) {
            $payment = $verified->first();
            if ($verified->count() === 1 && $invoice->status === DocumentStatusEnum::ISSUED
                && $payment->getAttribute('record_type') === SaasPayment::RECORD_TYPE
                && $payment->getAttribute('reversal_of_id') === null
                && $payment->getAttribute('user_id') === $invoice->user_id
                && $payment->getAttribute('currency') === $invoice->currency
                && $payment->getAttribute('amount') === $invoice->total_amount
                && $payment->getAttribute('validated_at') !== null) {
                return [PaymentStateEnum::PAID, $payment];
            }

            return [PaymentStateEnum::ANOMALY, null];
        }
        if ($transfers->contains(fn (SaasPayment $payment): bool => in_array($payment->getAttribute('transfer_status'),
            [TransferStatusEnum::DECLARED, TransferStatusEnum::APPROVED, TransferStatusEnum::UNCERTAIN], true))) {
            return [PaymentStateEnum::REVIEW, null];
        }

        return [$transfers->isEmpty() ? PaymentStateEnum::DUE : PaymentStateEnum::ANOMALY, null];
    }
}
