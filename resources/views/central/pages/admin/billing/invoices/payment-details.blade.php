@php($oldText = static fn (string $key, mixed $default = null): string => is_scalar($value = old($key, $default)) ? (string) $value : '')
<section id="payment-{{ $payment->uuid }}" class="flex flex-col gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
    <flux:heading>Paiement {{ $payment->uuid }}</flux:heading>
        <dl class="grid gap-4 sm:grid-cols-2">
            <div><dt>État</dt><dd>{{ $payment->statusLabel() }}</dd></div>
            <div><dt>Commerçant</dt><dd>{{ $payment->owner?->name }}</dd></div>
            <div><dt>Montant</dt><dd>{{ $payment->getAttribute('amount') }} {{ $payment->getAttribute('currency') }}</dd></div>
            <div><dt>Méthode</dt><dd>{{ $payment->getAttribute('transfer_method')->label() }}</dd></div>
            <div><dt>Référence interne</dt><dd>PAY-{{ $payment->getAttribute('uuid') }}</dd></div>
            <div><dt>Période</dt><dd>{{ $payment->invoice->period_starts_at->format('d/m/Y') }} — {{ $payment->invoice->period_ends_at->format('d/m/Y') }}</dd></div>
            <div><dt>Abonnement</dt><dd>{{ $payment->invoice->subscription->status->label() }}</dd></div>
            <div><dt>Date déclarée de réception</dt><dd>{{ $payment->getAttribute('occurred_at')?->format('d/m/Y H:i:s') }}</dd></div>
            <div><dt>Date de décision</dt><dd>{{ $payment->getAttribute('validated_at')?->format('d/m/Y H:i:s') ?? 'Pas encore décidée' }}</dd></div>
        </dl>
        @if ($payment->getAttribute('reason'))<flux:callout>{{ $payment->getAttribute('reason') }}</flux:callout>@endif
        @if ($payment->getAttribute('transfer_status') !== \App\Enums\Central\Billing\TransferStatusEnum::VERIFIED)
            <flux:text>Aucun droit payant n’est accordé par cette déclaration. Seule une réception intégrale confirmée permet de payer la période.</flux:text>
        @endif
        @if ($payment->getAttribute('proof_media_id'))
            <flux:button :href="\Illuminate\Support\Facades\URL::temporarySignedRoute($ownerMode ? 'owner.payments.proof' : 'central.payments.proof', now()->addMinutes(10), ['payment' => $payment])">Télécharger le justificatif privé</flux:button>
        @endif
        @can('receipt', $payment)
            <flux:button :href="\Illuminate\Support\Facades\URL::temporarySignedRoute($ownerMode ? 'owner.payments.receipt' : 'central.payments.receipt', now()->addMinutes(10), ['payment' => $payment])">Télécharger le reçu privé</flux:button>
        @endcan

        @if ($ownerMode)
            @can('supplyProof', $payment)
                <form method="POST" enctype="multipart/form-data" action="{{ route('owner.payments.resume-proof', $payment) }}" class="flex flex-col gap-3">
                    @csrf<input type="hidden" name="expected_state" value="{{ $payment->managementState() }}">
                    <flux:input type="file" name="proof" label="Compléter le justificatif manquant — photo ou PDF, 8 Mo maximum" accept="application/pdf,image/jpeg,image/png" required />
                    <flux:button type="submit">Reprendre le dépôt sans nouvelle déclaration</flux:button>
                </form>
            @endcan
            <form method="POST" action="{{ route('owner.billing.corrections.store', $payment) }}" class="flex flex-col gap-3">
                @csrf<input type="hidden" name="request_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <flux:textarea name="reason" label="Signaler une erreur sur ce paiement (10 à 2 000 caractères)" minlength="10" maxlength="2000" required />
                <flux:button type="submit">Envoyer le signalement</flux:button>
            </form>
            <flux:text>Ce signalement n'altère pas le paiement confirmé : l'administration doit examiner la demande.</flux:text>
        @endif

        @unless ($ownerMode)
            <flux:text>Enregistré par : {{ $payment->creator?->name ?? 'Auteur historique non renseigné' }}. Décision : {{ $payment->validator?->name ?? 'En attente' }}.</flux:text>
            @if ($payment->owner)
                @can('view', $payment->owner)<flux:button :href="route('central.users.show', $payment->owner)">Consulter le commerçant</flux:button>@endcan
            @endif
            @if ($payment->getAttribute('proof_media_id') === null && $payment->getAttribute('transfer_method') === \App\Enums\Central\Billing\TransferMethodEnum::CASH_IN_PERSON)
                @can('verify', $payment)
                    <form method="POST" action="{{ route('central.payments.resume-cash', $payment) }}" class="flex flex-col gap-3">
                        @csrf<input type="hidden" name="expected_state" value="{{ $payment->managementState() }}">
                        <flux:checkbox name="received" value="1" label="Je confirme les espèces déjà déclarées reçues et reprends leur constat interne." required />
                        <flux:button type="submit">Reprendre l’encaissement sans le recréer</flux:button>
                    </form>
                @endcan
            @endif
            @if ($payment->getAttribute('transfer_status') === \App\Enums\Central\Billing\TransferStatusEnum::UNCERTAIN)
                <flux:callout>Cette anomalie exige un traitement administratif et, si nécessaire, comptable ou légal. Elle ne sera pas complétée par des règlements partiels, ni remboursée automatiquement.</flux:callout>
                @can('reconcile', $payment)
                    <form method="POST" action="{{ route('central.payments.reconcile', $payment) }}" class="flex flex-col gap-3">
                        @csrf<input type="hidden" name="expected_state" value="{{ $payment->managementState() }}">
                        <flux:textarea name="review_note" label="Note administrative privée de rapprochement" required maxlength="2000">{{ $oldText('review_note', $payment->getAttribute('encrypted_transfer_details')['reconciliation_note'] ?? '') }}</flux:textarea>
                        <flux:button type="submit">Consigner le rapprochement de l’anomalie</flux:button>
                    </form>
                @endcan
            @endif
            @if ($payment->getAttribute('transfer_status') === \App\Enums\Central\Billing\TransferStatusEnum::DECLARED)
                @if ($payment->getAttribute('proof_media_id') !== null)
                    @if ($payment->getAttribute('transfer_method') === \App\Enums\Central\Billing\TransferMethodEnum::CASH_IN_PERSON)
                        @can('verify', $payment)
                            <form method="POST" action="{{ route('central.payments.resume-cash', $payment) }}" class="flex flex-col gap-3">
                                @csrf<input type="hidden" name="expected_state" value="{{ $payment->managementState() }}">
                                <flux:checkbox name="received" value="1" label="Je confirme les espèces déjà déclarées reçues." required />
                                <flux:button type="submit">Reprendre l’encaissement</flux:button>
                            </form>
                        @endcan
                    @else
                        @can('reconcile', $payment)
                            <form method="POST" action="{{ route('central.payments.reconcile', $payment) }}" class="flex flex-col gap-3">
                                @csrf<input type="hidden" name="expected_state" value="{{ $payment->managementState() }}">
                                <flux:textarea name="review_note" label="Note administrative privée" required maxlength="2000">{{ $oldText('review_note') }}</flux:textarea>
                                <flux:button type="submit">Consigner le contrôle sans confirmer les fonds</flux:button>
                            </form>
                        @endcan
                        @if ($payment->invoice->subscription->request)
                            @can('accept', $payment->invoice->subscription->request)
                                <flux:button :href="route('central.subscription-requests.show', $payment->invoice->subscription->request)">Consulter et décider de la demande</flux:button>
                            @endcan
                        @else
                            @can('verify', $payment)
                                <form method="POST" action="{{ route('central.payments.verify', $payment) }}" class="flex flex-col gap-3">
                                    @csrf<input type="hidden" name="expected_state" value="{{ $payment->managementState() }}">
                                    <flux:text>En confirmant, vous attestez la réception réelle du montant intégral des fonds.</flux:text>
                                    <flux:checkbox name="proof_reviewed" value="1" label="J’ai consulté et vérifié le justificatif." required />
                                    <flux:button type="submit" variant="primary">Confirmer le paiement</flux:button>
                                </form>
                            @endcan
                        @endif
                    @endif
                @endif
                @can('reject', $payment)
                    <form method="POST" action="{{ route('central.payments.reject', $payment) }}" class="flex flex-col gap-3">
                        @csrf<input type="hidden" name="expected_state" value="{{ $payment->managementState() }}">
                        <flux:textarea name="reason" label="Motif du refus — visible par le commerçant" required maxlength="2000">{{ $oldText('reason') }}</flux:textarea>
                        <flux:button type="submit">Refuser la déclaration</flux:button>
                    </form>
                @endcan
            @endif

            @if ($history !== null)
                <flux:heading>Historique</flux:heading>
                @foreach ($history as $event)
                    <flux:text>{{ $event->created_at->format('d/m/Y H:i') }} — {{ $event->description }}</flux:text>
                    @if ($event->event === \App\Services\Central\Billing\OwnerPaymentCorrectionService::EVENT)
                        <flux:callout>Motif communiqué par le commerçant : {{ $event->properties['reason'] ?? 'Motif non renseigné' }}</flux:callout>
                    @endif
                @endforeach
            @endif
        @endunless

</section>
