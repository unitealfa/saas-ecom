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
        @if (! in_array($paymentState, [\App\Enums\Central\Billing\PaymentStateEnum::PAID, \App\Enums\Central\Billing\PaymentStateEnum::CREDITED], true))
            @can('declarePayment', $invoice)<flux:button :href="route('owner.payments.create', $invoice)">Déposer un justificatif de versement</flux:button>@endcan
            @unless ($ownerMode)
                @can('recordCashPayment', $invoice)<flux:button :href="route('central.payments.cash.create', $invoice)">Enregistrer des espèces reçues</flux:button>@endcan
            @endunless
        @endif
        @can('viewOwn', \App\Models\Central\Billing\SaasPayment::class)<flux:button :href="route('owner.payments.index')">Mes paiements et reçus</flux:button>@endcan
        @unless ($ownerMode)
            @can('viewAny', \App\Models\Central\Billing\SaasPayment::class)<flux:button :href="route('central.payments.index')">Paiements et reçus</flux:button>@endcan
        @endunless
        @foreach ($invoice->getAttribute('taxes') as $tax)<flux:text>{{ $tax['code'] }} : {{ $tax['rate'] }} % — {{ $tax['amount'] }} {{ $invoice->currency }}</flux:text>@endforeach
        @can('download', $invoice)
            <flux:button :href="\Illuminate\Support\Facades\URL::temporarySignedRoute($ownerMode ? 'owner.invoices.download' : 'central.invoices.download', now()->addMinutes(10), ['invoice' => $invoice])">Télécharger le PDF privé</flux:button>
        @endcan
        @unless ($ownerMode)
            @if ($invoice->status !== \App\Enums\Central\Billing\DocumentStatusEnum::ISSUED && ! ($invoice->saas_identity_snapshot['preparation_only'] ?? false))
                @can('issue', $invoice)
                    <form method="POST" action="{{ route('central.invoices.issue', $invoice) }}" x-data="{ submitting: false }"
                        x-on:submit="if (! window.confirm('Émettre définitivement cette facture et figer son PDF ?')) { $event.preventDefault(); return; } submitting = true">
                        @csrf
                        <input type="hidden" name="expected_state" value="{{ $invoice->managementState() }}">
                        <flux:button type="submit" variant="primary" x-bind:disabled="submitting" x-bind:aria-busy="submitting">{{ $invoice->status === \App\Enums\Central\Billing\DocumentStatusEnum::PREPARING ? 'Reprendre l’émission' : 'Émettre la facture' }}</flux:button>
                        <flux:text x-cloak x-show="submitting" role="status">Génération et vérification du PDF en cours…</flux:text>
                    </form>
                @endcan
            @endif
            @if ($invoice->relationLoaded('deliveries') && $invoice->deliveries->isNotEmpty())<flux:text>Transmission : intention enregistrée, sans preuve de remise.</flux:text>@endif
            @if ($history !== null)
                <flux:heading>Historique</flux:heading>
                @foreach ($history as $event)<flux:text>{{ $event->created_at->format('d/m/Y H:i') }} — {{ $event->description }}</flux:text>@endforeach
                {{ $history->links() }}
            @endif
        @endunless
        @if ($invoice->sequence)
            <flux:text>Compteur {{ $invoice->sequence->prefix }}, exercice {{ $invoice->getAttribute('fiscal_year') }}, numéro réservé {{ $invoice->sequence_number }}.</flux:text>
        @endif
        @if ($invoice->status === \App\Enums\Central\Billing\DocumentStatusEnum::DRAFT && ($invoice->saas_identity_snapshot['preparation_only'] ?? false))
            @can('reserveNumber', $invoice)
                <form method="POST" action="{{ route('central.invoices.reserve', $invoice) }}">
                    @csrf
                    <input type="hidden" name="expected_state" value="{{ $invoice->managementState() }}">
                    <flux:button type="submit" variant="primary">Réserver le numéro et figer la préparation</flux:button>
                </form>
            @endcan
        @endif
        <flux:button :href="route($ownerMode ? 'owner.invoices.index' : 'central.invoices.index')">Retour aux factures</flux:button>
    </section>
</x-layouts::app>
