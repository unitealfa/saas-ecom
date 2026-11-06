@php
    $oldText = static fn (string $key, mixed $default = null): string => is_scalar($value = old($key, $default)) ? (string) $value : '';
@endphp
<x-layouts::app title="Paiement SaaS">
    <section class="mx-auto flex w-full max-w-4xl flex-col gap-6">
        <flux:heading size="xl" level="1">Paiement — {{ $payment->invoice->number }}</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
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
                @foreach (['reconcile' => 'Enregistrer le rapprochement sans confirmer les fonds', 'verify' => 'Confirmer la réception intégrale des fonds'] as $action => $label)
                    @can($action, $payment)
                        <form method="POST" action="{{ route('central.payments.'.$action, $payment) }}" class="flex flex-col gap-3">
                            @csrf<input type="hidden" name="expected_state" value="{{ $payment->managementState() }}">
                            <flux:input name="account" label="Compte destinataire réellement contrôlé (IBAN ou CCP)" :value="$oldText('account', $payment->getAttribute('encrypted_transfer_details')['account'] ?? ($payment->getAttribute('transfer_method') === \App\Enums\Central\Billing\TransferMethodEnum::CASH_IN_PERSON ? 'cash:central' : ''))" required />
                            <flux:input name="reference" label="Référence réelle contrôlée ou référence interne espèces" :value="$oldText('reference', $payment->getAttribute('transfer_reference'))" required />
                            <flux:input type="datetime-local" name="occurred_at" label="Date réelle de réception" :value="$oldText('occurred_at', $payment->getAttribute('occurred_at')->format('Y-m-d\TH:i:s'))" required />
                            @if ($action === 'verify')<flux:checkbox name="received" value="1" label="J’ai contrôlé la réception réelle du montant intégral, son destinataire et sa référence." required />@endif
                            <flux:button type="submit" variant="primary">{{ $label }}</flux:button>
                        </form>
                    @endcan
                @endforeach
                @endif
                @can('reject', $payment)
                    <form method="POST" action="{{ route('central.payments.reject', $payment) }}" class="flex flex-col gap-3">
                        @csrf<input type="hidden" name="expected_state" value="{{ $payment->managementState() }}">
                        <flux:textarea name="reason" label="Motif du refus — visible par le commerçant" required maxlength="2000">{{ $oldText('reason') }}</flux:textarea>
                        <flux:button type="submit">Refuser la déclaration</flux:button>
                    </form>
                @endcan
            @endif
            @if ($history !== null)<flux:heading>Historique</flux:heading>
                @foreach ($history as $event)<flux:text>{{ $event->created_at->format('d/m/Y H:i') }} — {{ $event->description }}</flux:text>@endforeach
                {{ $history->links() }}
            @endif
        @endunless
        <flux:button :href="route($ownerMode ? 'owner.invoices.show' : 'central.invoices.show', $payment->invoice)">Consulter la facture</flux:button>
        <flux:button :href="route($ownerMode ? 'owner.payments.index' : 'central.payments.index')">Retour aux paiements</flux:button>
    </section>
</x-layouts::app>
