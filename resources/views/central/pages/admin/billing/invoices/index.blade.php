<x-layouts::app title="Factures SaaS">
    <section class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <flux:heading size="xl" level="1">Factures SaaS</flux:heading>
        <flux:text>Les préparations numérotées restent distinctes des factures définitivement émises.</flux:text>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @can('viewAny', \App\Models\Central\Subscriptions\Subscription::class)
            <flux:button :href="route('central.subscriptions.index')">Choisir un abonnement à facturer</flux:button>
        @endcan
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <flux:input name="search" label="Numéro de facture" :value="request('search')" />
            <flux:button type="submit">Rechercher</flux:button>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3">Numéro</th><th class="p-3">Commerçant</th><th class="p-3">Montant</th><th class="p-3">État</th><th class="p-3">Actions</th></tr></thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td class="p-3">{{ $invoice->number ?? 'Non réservé' }}</td><td class="p-3">{{ $invoice->owner?->name }}</td>
                            <td class="p-3">{{ $invoice->total_amount }} {{ $invoice->currency }}</td><td class="p-3">{{ $invoice->status->label() }}</td>
                            <td class="p-3"><flux:button size="sm" :href="route('central.invoices.show', $invoice)">Informations</flux:button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-3">Aucune facture préparée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $invoices->links() }}
    </section>
</x-layouts::app>
