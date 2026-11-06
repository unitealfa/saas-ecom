@php
    $oldText = static fn (string $key, mixed $default = null): string => is_scalar($value = old($key, $default)) ? (string) $value : '';
@endphp
<x-layouts::app :title="$cash ? 'Enregistrer des espèces reçues' : 'Déposer un justificatif'">
    <section class="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $cash ? 'Enregistrer des espèces reçues' : 'Déposer un justificatif externe' }}</flux:heading>
        <flux:text>Facture {{ $invoice->number }} — montant intégral : {{ $invoice->total_amount }} {{ $invoice->currency }}.</flux:text>
        <flux:text>Période : {{ $invoice->period_starts_at->format('d/m/Y') }} — {{ $invoice->period_ends_at->format('d/m/Y') }}.</flux:text>
        <flux:callout>{{ config($cash ? 'central.billing.cash_instructions' : 'central.billing.payment_instructions') ?: 'Demandez à l’administration le lieu de remise des espèces ou les coordonnées de versement officielles avant tout paiement. Aucune coordonnée bancaire n’est inventée par Aydra.' }}</flux:callout>
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <form method="POST" enctype="multipart/form-data" class="flex flex-col gap-4" action="{{ route($cash ? 'central.payments.cash.store' : 'owner.payments.store', $invoice) }}">
            @csrf
            <input type="hidden" name="operation_id" value="{{ $oldText('operation_id', (string) \Illuminate\Support\Str::uuid()) }}">
            <input type="hidden" name="currency" value="{{ $invoice->currency }}">
            <flux:input name="amount" label="Montant réellement reçu ou déclaré" inputmode="decimal" :value="$oldText('amount', $invoice->total_amount)" required />
            <flux:input name="occurred_at" type="datetime-local" label="Date et heure réelles du paiement" :value="$oldText('occurred_at')" required />
            @if ($cash)
                <flux:checkbox name="received" value="1" label="Je confirme avoir physiquement reçu ces espèces." required />
                <flux:text>Un montant différent de la facture sera conservé comme anomalie, sans activer l’abonnement. Aucun justificatif bancaire n’est demandé.</flux:text>
            @else
                <flux:select name="method" label="Moyen du versement externe" required>
                    @foreach ([\App\Enums\Central\Billing\TransferMethodEnum::BANK_TRANSFER, \App\Enums\Central\Billing\TransferMethodEnum::CCP_TRANSFER, \App\Enums\Central\Billing\TransferMethodEnum::BARIDIMOB] as $method)
                        <option value="{{ $method->value }}" @selected($oldText('method') === (string) $method->value)>{{ $method->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input name="reference" label="Référence du justificatif, si disponible" :value="$oldText('reference')" />
                <flux:input type="file" name="proof" label="Photo JPEG/PNG ou PDF — 8 Mo maximum" accept="application/pdf,image/jpeg,image/png" required />
                <flux:text>Utilisez un versement déjà effectué hors Aydra. L’administration vérifiera la réception réelle des fonds. Aucun paiement par carte n’est proposé ici.</flux:text>
            @endif
            <flux:button type="submit" variant="primary">{{ $cash ? 'Enregistrer l’encaissement reçu' : 'Déposer le justificatif' }}</flux:button>
        </form>
        <flux:button :href="route($cash ? 'central.invoices.show' : 'owner.invoices.show', $invoice)">Retour à la facture</flux:button>
    </section>
</x-layouts::app>
