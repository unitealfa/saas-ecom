<x-layouts::app :title="$invoice->number ?? 'Préparation de facture'">
    <section class="mx-auto flex w-full max-w-4xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $invoice->number ?? 'Facture — numéro non réservé' }}</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <flux:callout>Cette facture n’est pas émise. Aucun PDF ni paiement n’a été créé. Le numéro réservé ne sera pas réutilisé.</flux:callout>
        <dl class="grid gap-4 sm:grid-cols-2">
            <div><dt>État</dt><dd>{{ $invoice->status->label() }}</dd></div>
            <div><dt>Commerçant au moment de la préparation</dt><dd>{{ $invoice->customer_identity_snapshot['first_name'] ?? '' }} {{ $invoice->customer_identity_snapshot['last_name'] ?? '' }}</dd></div>
            <div><dt>Échéance</dt><dd>{{ $invoice->installment->installment_number }}</dd></div>
            <div><dt>Règle appliquée</dt><dd>{{ $invoice->billing_rule_snapshot['code'] }} — version {{ $invoice->billing_rule_snapshot['version'] }}</dd></div>
            <div><dt>Émetteur déclaré</dt><dd>{{ $invoice->saas_identity_snapshot['name'] }}</dd></div>
            <div><dt>Période</dt><dd>{{ $invoice->period_starts_at->format('d/m/Y') }} — {{ $invoice->period_ends_at->format('d/m/Y') }}</dd></div>
            <div><dt>Date limite de paiement</dt><dd>{{ $invoice->due_at->format('d/m/Y') }}</dd></div>
            <div><dt>Préparation sans taxe</dt><dd>{{ $invoice->billing_rule_snapshot['no_tax_reason'] ?? 'Non renseigné' }}</dd></div>
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
        @if ($invoice->sequence)
            <flux:text>Compteur {{ $invoice->sequence->prefix }}, exercice {{ $invoice->getAttribute('fiscal_year') }}, numéro réservé {{ $invoice->sequence_number }}.</flux:text>
        @endif
        @if ($invoice->status === \App\Enums\Central\Billing\DocumentStatusEnum::DRAFT)
            @can('reserveNumber', $invoice)
                <form method="POST" action="{{ route('central.invoices.reserve', $invoice) }}">
                    @csrf
                    <input type="hidden" name="expected_state" value="{{ $invoice->managementState() }}">
                    <flux:button type="submit" variant="primary">Réserver le numéro et figer la préparation</flux:button>
                </form>
            @endcan
        @endif
        <flux:button :href="route('central.invoices.index')">Retour aux factures</flux:button>
    </section>
</x-layouts::app>
