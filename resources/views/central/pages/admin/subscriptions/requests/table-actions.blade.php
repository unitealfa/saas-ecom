@php
    $cash = $item->payment_choice === \App\Enums\Central\Subscriptions\PaymentChoiceEnum::CASH;
    $zeroPrice = $item->subscription?->agreed_amount === '0.00';
    $initialProof = $item->getAttribute('submitted_payment') !== null;
    $paymentProof = $item->payment?->getAttribute('proof_media_id') !== null;
    $canViewInitial = $initialProof && \Illuminate\Support\Facades\Gate::allows('viewInitialProof', $item);
    $canViewPayment = $paymentProof && \Illuminate\Support\Facades\Gate::allows('view', $item->payment);
    $proofUrl = $canViewInitial
        ? \Illuminate\Support\Facades\URL::temporarySignedRoute('central.subscription-requests.initial-proof', now()->addMinutes(10), ['subscriptionRequest' => $item, 'inline' => 1])
        : ($canViewPayment ? \Illuminate\Support\Facades\URL::temporarySignedRoute('central.payments.proof', now()->addMinutes(10), ['payment' => $item->payment, 'inline' => 1]) : null);
@endphp
@if (! $item->status->isFinal() && ($item->payment === null || $item->payment->getAttribute('transfer_status') !== \App\Enums\Central\Billing\TransferStatusEnum::UNCERTAIN))
    @can('accept', $item)
        @if ($cash || $initialProof || $paymentProof)
            <div x-data="{ open: false }">
                <flux:modal.trigger :name="'accept-request-'.$item->uuid"><flux:button type="button" x-on:click="open = true">Accepter la demande</flux:button></flux:modal.trigger>
                <flux:modal :name="'accept-request-'.$item->uuid" class="w-full md:max-w-2xl">
                    <form method="POST" action="{{ route('central.subscription-requests.accept', $item) }}" class="flex flex-col gap-4">
                        @csrf
                        <flux:heading size="lg">Accepter la demande</flux:heading>
                        <flux:text>{{ $item->owner?->name }} — {{ $item->subscription?->plan?->name }} — {{ $item->subscription?->agreed_amount }} DZD</flux:text>
                        <input type="hidden" name="expected_state" value="{{ $item->managementState() }}">
                        <input type="hidden" name="expected_payment_state" value="{{ $item->payment?->managementState() }}">
                        @if ($proofUrl)
                            <template x-if="open"><iframe src="{{ $proofUrl }}" title="Justificatif de paiement" class="h-80 w-full rounded border"></iframe></template>
                            <a href="{{ $proofUrl }}" target="_blank" rel="noopener" class="underline">Ouvrir le justificatif</a>
                        @elseif ($initialProof || $paymentProof)
                            <flux:callout variant="warning">Vous ne disposez pas du droit de consulter le justificatif.</flux:callout>
                        @endif
                        @if ($zeroPrice)
                            <flux:text>En acceptant, vous approuvez l’offre et émettez la facture à 0,00 DZD.</flux:text>
                        @else
                            <flux:text>En acceptant, vous confirmez avoir reçu et contrôlé le montant intégral.</flux:text>
                        @endif
                        @if (filled($item->getAttribute('invoice_error')))
                            <flux:callout variant="warning">{{ $item->getAttribute('invoice_error') }}</flux:callout>
                            <flux:checkbox name="acknowledged" value="1" label="J’ai lu et approuvé ce message." required />
                        @endif
                        @if (! $cash && ! $zeroPrice)
                            <flux:checkbox name="proof_reviewed" value="1" label="J’ai consulté et vérifié le justificatif." required />
                        @endif
                        <flux:checkbox name="decision_confirmed" value="1" label="J’ai lu et j’approuve cette décision." required />
                        <div class="flex gap-2">
                            <flux:button type="submit" variant="primary" :disabled="($initialProof || $paymentProof) && ! $proofUrl">Valider</flux:button>
                            <flux:modal.close><flux:button type="button" x-on:click="open = false">Annuler</flux:button></flux:modal.close>
                        </div>
                    </form>
                </flux:modal>
            </div>
        @endif
    @endcan
@endif
@can('reject', $item)
    <flux:modal.trigger :name="'reject-request-'.$item->uuid"><flux:button type="button" variant="danger">Refuser</flux:button></flux:modal.trigger>
    <flux:modal :name="'reject-request-'.$item->uuid" class="md:w-96">
        <form method="POST" action="{{ route('central.subscription-requests.reject', $item) }}" class="flex flex-col gap-4">
            @csrf
            <flux:heading size="lg">Refuser la demande</flux:heading>
            <input type="hidden" name="expected_state" value="{{ $item->managementState() }}">
            <input type="hidden" name="expected_payment_state" value="{{ $item->payment?->managementState() }}">
            <flux:textarea name="reason" label="Motif du refus, visible par le commerçant" required maxlength="2000" />
            <div class="flex gap-2"><flux:button type="submit" variant="danger">Refuser avec motif</flux:button><flux:modal.close><flux:button type="button">Annuler</flux:button></flux:modal.close></div>
        </form>
    </flux:modal>
@endcan
