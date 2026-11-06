<x-layouts::app :title="$ownerMode ? 'Mes paiements' : 'Paiements SaaS'">
    <section class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $ownerMode ? 'Mes paiements' : 'Paiements SaaS' }}</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        <flux:text>Chaque période se règle intégralement, sur place en espèces ou par versement externe avec justificatif. Un dépôt de fichier ne confirme pas la réception des fonds.</flux:text>
        <form method="GET" class="flex flex-wrap gap-3">
            <flux:input name="search" label="Référence publique ou facture" :value="request('search')" />
            <flux:select name="status" label="État"><option value="">Tous</option>
                @foreach (\App\Enums\Central\Billing\TransferStatusEnum::cases() as $status)<option value="{{ $status->value }}" @selected((string) request('status') === (string) $status->value)>{{ $status->label() }}</option>@endforeach
            </flux:select>
            <flux:button type="submit">Rechercher</flux:button>
        </form>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm">
            <thead><tr><th class="p-3">Facture</th><th class="p-3">Commerçant</th><th class="p-3">Montant</th><th class="p-3">Méthode</th><th class="p-3">État</th><th class="p-3">Actions</th></tr></thead>
            <tbody>@forelse ($payments as $payment)<tr class="border-t border-zinc-200 dark:border-zinc-700">
                <td class="p-3">{{ $payment->invoice->number }}</td><td class="p-3">{{ $payment->owner?->name }}</td>
                <td class="p-3">{{ $payment->getAttribute('amount') }} {{ $payment->getAttribute('currency') }}</td>
                <td class="p-3">{{ $payment->getAttribute('transfer_method')->label() }}</td><td class="p-3">{{ $payment->statusLabel() }}</td>
                <td class="p-3"><flux:button size="sm" :href="route($ownerMode ? 'owner.payments.show' : 'central.payments.show', $payment)">Informations</flux:button></td>
            </tr>@empty<tr><td colspan="6" class="p-3">Aucun paiement déclaré.</td></tr>@endforelse</tbody>
        </table></div>
        {{ $payments->links() }}
        <flux:button :href="route($ownerMode ? 'owner.invoices.index' : 'central.invoices.index')">Consulter les factures</flux:button>
    </section>
</x-layouts::app>
