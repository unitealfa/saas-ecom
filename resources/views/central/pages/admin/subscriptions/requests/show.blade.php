@php
    $item = $subscriptionRequest;
    $cash = $item->payment_choice === \App\Enums\Central\Subscriptions\PaymentChoiceEnum::CASH;
    $prefix = $ownerMode ? 'owner' : 'central';
    $issued = $item->invoice?->status === \App\Enums\Central\Billing\DocumentStatusEnum::ISSUED;
@endphp
<x-layouts::app title="Demande d’abonnement">
    <section class="mx-auto flex w-full max-w-4xl flex-col gap-6">
        <flux:heading size="xl" level="1">Demande d’abonnement — {{ $item->subscription?->plan->name }}</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <dl class="grid gap-4 sm:grid-cols-2">
            <div><dt>Commerçant</dt><dd>{{ $item->owner?->name }}</dd></div>
            <div><dt>État</dt><dd>{{ $item->status->label() }}</dd></div>
            <div><dt>Montant TTC</dt><dd>{{ $item->subscription?->agreed_amount }} DZD</dd></div>
            <div><dt>Moyen choisi</dt><dd>{{ $item->payment_choice->label() }}</dd></div>
            <div><dt>Période</dt><dd>{{ $item->subscription?->period_starts_at?->format('d/m/Y') }} — {{ $item->subscription?->period_ends_at?->format('d/m/Y') }}</dd></div>
            <div><dt>Abonnement</dt><dd>{{ $item->subscription?->status->label() }}</dd></div>
        </dl>
        @if ($item->getAttribute('decision_reason'))<flux:callout heading="Motif du refus">{{ $item->getAttribute('decision_reason') }}</flux:callout>@endif
        @if ($issued)
            <flux:text>Facture {{ $item->invoice->number }}. Elle constate le montant dû ; elle ne prouve pas un paiement.</flux:text>
            @can($ownerMode ? 'viewOwnInvoice' : 'view', $item->invoice)
                <flux:button :href="route($prefix.'.invoices.show', $item->invoice)">Consulter la facture</flux:button>
            @endcan
            @can('download', $item->invoice)
                <flux:button :href="\Illuminate\Support\Facades\URL::temporarySignedRoute($prefix.'.invoices.download', now()->addMinutes(10), ['invoice' => $item->invoice, 'inline' => 1])" target="_blank">Ouvrir le PDF de facture pour télécharger ou imprimer</flux:button>
            @endcan
        @else
            <flux:callout>{{ $item->getAttribute('invoice_error') ?? 'La facture est en préparation. Aucun paiement ne doit être confirmé à ce stade.' }}</flux:callout>
            @can('prepareInvoice', $item)
                <form method="POST" action="{{ route($prefix.'.subscription-requests.invoice', $item) }}">@csrf
                    <input type="hidden" name="expected_state" value="{{ $item->managementState() }}">
                    <flux:button type="submit">Reprendre la préparation de la facture</flux:button>
                </form>
            @endcan
        @endif
        @if ($item->payment)
            <flux:text>Paiement : {{ $item->payment->statusLabel() }}. Une photo seule ne prouve pas la réception des fonds.</flux:text>
            @if ($item->payment->hasReportedFunds() && ! $item->status->isFinal())
                <flux:callout>Une réception de fonds est signalée. La demande ne peut pas être corrigée ou annulée commercialement ; l’administration doit terminer le contrôle ou le rapprochement.</flux:callout>
            @endif
            @can('view', $item->payment)
                <flux:button :href="route($prefix.'.payments.show', $item->payment)">Consulter le paiement et son justificatif</flux:button>
            @endcan
            @can('receipt', $item->payment)
                <flux:button :href="\Illuminate\Support\Facades\URL::temporarySignedRoute($prefix.'.payments.receipt', now()->addMinutes(10), ['payment' => $item->payment, 'inline' => 1])" target="_blank">Ouvrir le reçu pour télécharger ou imprimer</flux:button>
            @endcan
        @endif
        @if ($ownerMode && ! $item->status->isFinal())
            @can('update', $item)
                <flux:callout>Pour changer l’offre ou la période après émission, demandez d’abord à l’administration une correction documentaire manuelle complète. La facture initiale reste conservée ; aucun avoir automatique.</flux:callout>
                <form method="POST" action="{{ route('owner.subscription-requests.revise', $item) }}" class="flex flex-col gap-3">@csrf @method('PUT')
                    <input type="hidden" name="expected_state" value="{{ $item->managementState() }}">
                    <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    @php($paidPlans = array_values(array_filter($plans, fn ($plan) => $plan['code'] !== 'free')))
                    <div x-data="{ plans: @js($paidPlans), plan: @js($item->subscription->plan->uuid), period: @js((string) $item->subscription->period->value) }" class="flex flex-col gap-3">
                        <flux:select name="plan_uuid" label="Nouvelle offre" x-model="plan" required>
                            @foreach ($paidPlans as $plan)<option value="{{ $plan['uuid'] }}">{{ $plan['name'] }} — {{ $plan['monthly_price'] }} DZD/mois, {{ $plan['annual_price'] }} DZD/an</option>@endforeach
                        </flux:select>
                        <flux:select name="period" label="Période" x-model="period" required><option value="1">Mensuelle</option><option value="2">Annuelle</option></flux:select>
                        <input type="hidden" name="expected_amount" x-bind:value="plans.find(p => p.uuid === plan)?.[period === '1' ? 'monthly_price' : 'annual_price'] ?? ''">
                    </div>
                    <input type="hidden" name="payment_choice" value="{{ $item->payment_choice->value }}">
                    <flux:button type="submit">Modifier l’offre ou la période</flux:button>
                </form>
                <form method="POST" action="{{ route('owner.subscription-requests.cancel', $item) }}">@csrf
                    <input type="hidden" name="expected_state" value="{{ $item->managementState() }}">
                    <flux:button type="submit">Annuler la demande non payée</flux:button>
                </form>
                <form method="POST" action="{{ route('owner.subscription-requests.choice', $item) }}" class="flex flex-col gap-3">@csrf @method('PATCH')
                    <input type="hidden" name="expected_state" value="{{ $item->managementState() }}">
                    <flux:select name="payment_choice" label="Modifier mon moyen de paiement">
                        @foreach (\App\Enums\Central\Subscriptions\PaymentChoiceEnum::cases() as $choice)<option value="{{ $choice->value }}" @selected($item->payment_choice === $choice)>{{ $choice->label() }}</option>@endforeach
                    </flux:select>
                    <flux:button type="submit">Enregistrer mon choix</flux:button>
                </form>
                @if ($cash)<flux:callout>Présentez votre facture au bureau Aydra. Seul l’administrateur peut constater les espèces réellement reçues. Aucun droit ne sera activé avant ce contrôle. {{ config('central.billing.cash_instructions') }}</flux:callout>
                @elseif ($issued)
                    <flux:callout>{{ config('central.billing.payment_instructions') ?: 'Demandez les coordonnées officielles de versement à l’administration avant tout envoi d’argent.' }}</flux:callout>
                    <form method="POST" enctype="multipart/form-data" action="{{ route('owner.subscription-requests.proof', $item) }}" class="flex flex-col gap-3">@csrf
                        <input type="hidden" name="expected_state" value="{{ $item->managementState() }}"><input type="hidden" name="currency" value="DZD">
                        <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <flux:select name="method" label="Réseau du versement externe" required>
                            @foreach ([\App\Enums\Central\Billing\TransferMethodEnum::BANK_TRANSFER, \App\Enums\Central\Billing\TransferMethodEnum::CCP_TRANSFER, \App\Enums\Central\Billing\TransferMethodEnum::BARIDIMOB] as $method)<option value="{{ $method->value }}">{{ $method->label() }}</option>@endforeach
                        </flux:select>
                        <flux:input name="amount" label="Montant réellement versé (DZD)" :value="$item->subscription->agreed_amount" required />
                        <flux:input type="datetime-local" name="occurred_at" label="Date du versement" required />
                        <flux:input name="reference" label="Référence du versement (si disponible)" maxlength="255" />
                        <flux:input type="file" name="proof" label="Photo ou PDF du justificatif — 8 Mo maximum" accept="application/pdf,image/jpeg,image/png" required />
                        <flux:button type="submit">Soumettre le justificatif pour vérification</flux:button>
                    </form>
                @endif
            @endcan
        @elseif (! $ownerMode && ! $item->status->isFinal())
            @if ($issued && ($item->payment === null || $item->payment->getAttribute('transfer_status') !== \App\Enums\Central\Billing\TransferStatusEnum::UNCERTAIN))
                @can('accept', $item)
                    @if ($cash || $item->payment)
                        <form method="POST" action="{{ route('central.subscription-requests.accept', $item) }}" class="flex flex-col gap-3">@csrf
                            <input type="hidden" name="expected_state" value="{{ $item->managementState() }}">
                            <input type="hidden" name="expected_payment_state" value="{{ $item->payment?->managementState() }}">
                            @if ($cash && ! $item->payment)
                                <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="currency" value="DZD">
                                <flux:input name="amount" label="Espèces réellement reçues (DZD)" required />
                            @endif
                            @if (! $cash)
                                <flux:input name="account" label="Compte destinataire réellement contrôlé" required maxlength="255" />
                                <flux:input name="reference" label="Référence réelle contrôlée" required maxlength="255" />
                            @endif
                            @if (! $cash || ! $item->payment)<flux:input type="datetime-local" name="occurred_at" label="Date réelle de réception" required />@endif
                            <flux:checkbox name="received" value="1" label="J’ai contrôlé la réception réelle du montant intégral et sa correspondance avec cette facture." required />
                            <flux:button type="submit" variant="primary">Confirmer les fonds et accepter la demande</flux:button>
                        </form>
                    @endif
                @endcan
            @endif
            @can('reject', $item)
                <form method="POST" action="{{ route('central.subscription-requests.reject', $item) }}" class="flex flex-col gap-3">@csrf
                    <input type="hidden" name="expected_state" value="{{ $item->managementState() }}"><input type="hidden" name="expected_payment_state" value="{{ $item->payment?->managementState() }}">
                    <flux:textarea name="reason" label="Motif du refus, visible par le commerçant" required maxlength="2000" />
                    <flux:button type="submit">Refuser avec motif</flux:button>
                </form>
            @endcan
        @endif
        @if ($history !== null)
            <flux:heading>Historique des décisions et corrections</flux:heading>
            @foreach ($history as $event)
                <flux:text>{{ $event->created_at->format('d/m/Y H:i') }} — {{ $event->description }}</flux:text>
                @if (is_string($event->getProperty('reason')) && $event->getProperty('reason') !== '')<flux:text>Motif conservé : {{ $event->getProperty('reason') }}</flux:text>@endif
            @endforeach
            {{ $history->links() }}
        @endif
        <flux:button :href="route($prefix.'.subscription-requests.index')">Retour aux demandes</flux:button>
    </section>
</x-layouts::app>
