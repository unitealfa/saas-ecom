<x-layouts::app :title="$invoice->number ?? 'Préparation de facture'">
    <section class="mx-auto flex w-full max-w-4xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $invoice->number ?? 'Facture — numéro non réservé' }}</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        @if ($invoice->status === \App\Enums\Central\Billing\DocumentStatusEnum::ISSUED)
            <flux:callout>Facture émise et PDF conservé. Cette émission ne confirme aucun paiement.</flux:callout>
        @else
            <flux:callout>Cette facture n’est pas émise. Le numéro réservé ne sera pas réutilisé. Une reprise conserve les informations figées.</flux:callout>
        @endif
        @if ($invoice->saas_identity_snapshot['preparation_only'] ?? false)
            <flux:text>Préparation limitée de démonstration : les identités fiscales complètes ne sont pas présentes, l’émission définitive est indisponible.</flux:text>
        @endif
        @if ($ownerMode || \Illuminate\Support\Facades\Gate::allows('view', $invoice))
        <dl class="grid gap-4 sm:grid-cols-2">
            <div><dt>État</dt><dd>{{ $invoice->status->label() }}</dd></div>
            <div><dt>Commerçant au moment de la préparation</dt><dd>{{ $invoice->customer_identity_snapshot['name'] ?? trim(($invoice->customer_identity_snapshot['first_name'] ?? '').' '.($invoice->customer_identity_snapshot['last_name'] ?? '')) }}</dd></div>
            <div><dt>Échéance</dt><dd>{{ $invoice->installment->installment_number }}</dd></div>
            <div><dt>État du paiement</dt><dd>{{ $paymentState->label() }}</dd></div>
            <div><dt>Règle appliquée</dt><dd>{{ $invoice->billing_rule_snapshot['code'] }} — version {{ $invoice->billing_rule_snapshot['version'] }}</dd></div>
            <div><dt>Émetteur déclaré</dt><dd>{{ $invoice->saas_identity_snapshot['name'] }}</dd></div>
            <div><dt>Période</dt><dd>{{ $invoice->period_starts_at->format('d/m/Y') }} — {{ $invoice->period_ends_at->format('d/m/Y') }}</dd></div>
            <div><dt>Date limite de paiement</dt><dd>{{ $invoice->due_at->format('d/m/Y') }}</dd></div>
            <div><dt>Justification sans taxe</dt><dd>{{ $invoice->billing_rule_snapshot['fiscal_policy']['no_tax_reason'] ?? $invoice->billing_rule_snapshot['no_tax_reason'] ?? 'Taxes détaillées ci-dessous' }}</dd></div>
        </dl>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3">Description</th><th class="p-3">Quantité</th><th class="p-3">Montant net</th><th class="p-3">Taxe</th><th class="p-3">Total</th></tr></thead>
                <tbody>
                    @foreach ($invoice->lines as $line)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700"><td class="p-3">{{ $line->description }}</td><td class="p-3">{{ $line->quantity }}</td>
                            <td class="p-3">{{ $line->net_amount }}</td><td class="p-3">{{ $line->tax_amount }}</td><td class="p-3">{{ $line->total_amount }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
        <flux:heading>Total : {{ $invoice->total_amount }} {{ $invoice->currency }}</flux:heading>
        <flux:text>Avoirs émis : {{ $balance['credits_total'] }} — dû net : {{ $balance['net_due'] }} — reste à payer : {{ $balance['remaining'] }} — surplus : {{ $balance['surplus'] }} {{ $invoice->currency }}.</flux:text>
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
        @elseif (! in_array($paymentState, [\App\Enums\Central\Billing\PaymentStateEnum::PAID, \App\Enums\Central\Billing\PaymentStateEnum::CREDITED], true))
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
