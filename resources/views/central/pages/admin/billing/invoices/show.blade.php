<x-layouts::app :title="$invoice->number ?? 'Préparation de facture'">
    <section class="mx-auto flex w-full max-w-screen-xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">{{ $invoice->number ?? 'Facture — numéro non réservé' }}</h1><p class="aydra-muted text-sm sm:text-base">Consultez le document, son paiement et les opérations associées.</p></div><flux:button :href="route($ownerMode ? 'owner.billing.index' : 'central.invoices.index')" icon="arrow-left">Retour aux factures</flux:button></header>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        @if ($invoice->status === \App\Enums\Central\Billing\DocumentStatusEnum::ISSUED)
            <flux:callout>Facture émise et PDF conservé. Cette émission ne confirme aucun paiement.</flux:callout>
        @else
            <flux:callout>Cette facture n’est pas émise. Le numéro réservé ne sera pas réutilisé. Une reprise conserve les informations figées.</flux:callout>
        @endif
        @if ($invoice->saas_identity_snapshot['preparation_only'] ?? false)
            <flux:text>Préparation limitée de démonstration : les identités fiscales complètes ne sont pas présentes, l’émission définitive est indisponible.</flux:text>
        @endif
        @if ($ownerMode || \Illuminate\Support\Facades\Gate::allows('view', $invoice))
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading mb-5 text-lg font-semibold">Informations de la facture</h2>
        <dl class="aydra-detail-grid grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
            <div><dt>État</dt><dd>{{ $invoice->status->label() }}</dd></div>
            <div><dt>Commerçant au moment de la préparation</dt><dd>{{ $invoice->customer_identity_snapshot['name'] ?? trim(($invoice->customer_identity_snapshot['first_name'] ?? '').' '.($invoice->customer_identity_snapshot['last_name'] ?? '')) }}</dd></div>
            <div><dt>Échéance</dt><dd>{{ $invoice->installment->installment_number }}</dd></div>
            <div><dt>État du paiement</dt><dd>{{ $paymentState->label() }}</dd></div>
            @if (isset($invoice->billing_rule_snapshot['approval_actor_name']))
                <div><dt>Offre approuvée par</dt><dd>{{ $invoice->billing_rule_snapshot['approval_actor_name'] }}</dd></div>
            @endif
            <div><dt>Règle appliquée</dt><dd>{{ $invoice->billing_rule_snapshot['code'] }} — version {{ $invoice->billing_rule_snapshot['version'] }}</dd></div>
            <div><dt>Émetteur déclaré</dt><dd>{{ $invoice->saas_identity_snapshot['name'] }}</dd></div>
            <div><dt>Période</dt><dd>{{ $invoice->period_starts_at->format('d/m/Y') }} — {{ $invoice->period_ends_at->format('d/m/Y') }}</dd></div>
            <div><dt>Date limite de paiement</dt><dd>{{ $invoice->due_at->format('d/m/Y') }}</dd></div>
            <div><dt>Justification sans taxe</dt><dd>{{ $invoice->billing_rule_snapshot['fiscal_policy']['no_tax_reason'] ?? $invoice->billing_rule_snapshot['no_tax_reason'] ?? 'Taxes détaillées ci-dessous' }}</dd></div>
        </dl>
        </section>
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading mb-4 text-lg font-semibold">Lignes de facturation</h2><div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns><flux:table.column>Description</flux:table.column><flux:table.column>Quantité</flux:table.column><flux:table.column>Montant net</flux:table.column><flux:table.column>Taxe</flux:table.column><flux:table.column>Total</flux:table.column></flux:table.columns>
                <flux:table.rows>
                    @foreach ($invoice->lines as $line)
                        <flux:table.row><flux:table.cell>{{ $line->description }}</flux:table.cell><flux:table.cell>{{ $line->quantity }}</flux:table.cell>
                            <flux:table.cell>{{ $line->net_amount }}</flux:table.cell><flux:table.cell>{{ $line->tax_amount }}</flux:table.cell><flux:table.cell>{{ $line->total_amount }}</flux:table.cell></flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div></section>
        @endif
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading text-lg font-semibold">Montant et solde</h2><p class="aydra-heading mt-3 text-2xl font-semibold tabular-nums">{{ $invoice->total_amount }} {{ $invoice->currency }}</p><p class="aydra-muted mt-2 text-sm">Avoirs émis : {{ $balance['credits_total'] }} — dû net : {{ $balance['net_due'] }} — reste à payer : {{ $balance['remaining'] }} — surplus : {{ $balance['surplus'] }} {{ $invoice->currency }}.</p></section>
        @foreach ($credits as $credit)
            @if ($ownerMode)
                <flux:button :href="route('owner.credit-notes.show', $credit)">{{ $credit->number }} — {{ $credit->total_amount }} {{ $credit->currency }}</flux:button>
            @else
                @can('view', $credit)<flux:button :href="route('central.credit-notes.show', $credit)">{{ $credit->number }} — {{ $credit->total_amount }} {{ $credit->currency }}</flux:button>@endcan
            @endif
        @endforeach
        @if ($invoice->status === \App\Enums\Central\Billing\DocumentStatusEnum::ISSUED)
            @unless ($ownerMode)
                @can('create', \App\Models\Central\Billing\SaasCreditNote::class)<flux:button :href="route('central.credit-notes.create', $invoice)">Préparer une correction documentaire manuelle</flux:button>@endcan
            @endunless
        @endif
        <flux:text>La facture indique le montant intégral dû pour cette période. Son émission n’active pas les droits payants.</flux:text>
        <flux:text>Le reçu est disponible dans le paiement après confirmation intégrale des fonds. Le PDF de cette facture reste inchangé.</flux:text>
        @if ($invoice->subscription->request)
            @can('view', $invoice->subscription->request)
                <flux:button :href="route(($ownerMode ? 'owner' : 'central').'.subscription-requests.show', $invoice->subscription->request)">Demande, justificatifs et décision</flux:button>
            @endcan
        @elseif (! in_array($paymentState, [\App\Enums\Central\Billing\PaymentStateEnum::PAID, \App\Enums\Central\Billing\PaymentStateEnum::CREDITED, \App\Enums\Central\Billing\PaymentStateEnum::NO_PAYMENT_REQUIRED], true))
            @if ($ownerMode)
                @can('declarePayment', $invoice)@include('central.pages.admin.billing.invoices.payment-form', ['cash' => false])@endcan
            @else
                @can('recordCashPayment', $invoice)@include('central.pages.admin.billing.invoices.payment-form', ['cash' => true])@endcan
            @endif
        @endif
        @foreach ($invoice->getAttribute('taxes') as $tax)<flux:text>{{ $tax['code'] }} : {{ $tax['rate'] }} % — {{ $tax['amount'] }} {{ $invoice->currency }}</flux:text>@endforeach
        @foreach ($invoicePayments as $payment)
            @can('view', $payment)
                @include('central.pages.admin.billing.invoices.payment-details', ['history' => $ownerMode ? null : $paymentHistories->get($payment->id, collect())])
            @endcan
        @endforeach
        {{ $invoicePayments->links() }}
        @can('download', $invoice)
            <flux:button :href="\Illuminate\Support\Facades\URL::temporarySignedRoute($ownerMode ? 'owner.invoices.download' : 'central.invoices.download', now()->addMinutes(10), ['invoice' => $invoice, 'inline' => 1])" target="_blank" rel="noopener">Voir / imprimer la facture</flux:button>
            <flux:button :href="\Illuminate\Support\Facades\URL::temporarySignedRoute($ownerMode ? 'owner.invoices.download' : 'central.invoices.download', now()->addMinutes(10), ['invoice' => $invoice])">Télécharger le PDF privé</flux:button>
        @endcan
        @unless ($ownerMode)
            @unless ($ownerMode)
                @include('central.pages.admin.billing.document-delivery', ['document' => $invoice])
            @endunless
            @if ($history !== null)
                <flux:heading>Historique</flux:heading>
                @foreach ($history as $event)<flux:text>{{ $event->created_at->format('d/m/Y H:i') }} — {{ $event->description }}</flux:text>@endforeach
                {{ $history->links() }}
            @endif
        @endunless
        <flux:button :href="route($ownerMode ? 'owner.billing.index' : 'central.invoices.index')">Retour aux factures</flux:button>
        @if (! $ownerMode && $correctionReports !== null)
            @include('central.pages.admin.billing.invoices.correction-reports', $correctionReports)
        @endif
    </section>
</x-layouts::app>
