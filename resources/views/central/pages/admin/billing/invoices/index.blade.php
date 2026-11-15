<x-layouts::app :title="$ownerMode ? 'Mes factures et avoirs' : 'Factures SaaS'">
    <section class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $ownerMode ? 'Mes factures et avoirs' : 'Factures SaaS' }}</flux:heading>
        <flux:text>Les préparations numérotées restent distinctes des factures définitivement émises.</flux:text>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @can('viewAny', \App\Models\Central\Subscriptions\Subscription::class)
            <flux:button :href="route('central.subscriptions.index')">Choisir un abonnement à facturer</flux:button>
        @endcan
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <flux:select name="filter" label="Facturation">
                @foreach (\App\Enums\Central\Billing\InvoiceListFilterEnum::cases() as $filter)
                    <option value="{{ $filter->value }}" @selected(request('filter', 'all') === $filter->value)>{{ $filter->label() }}</option>
                @endforeach
            </flux:select>
            <flux:input name="search" label="Numéro de facture" :value="request('search')" />
            @unless ($ownerMode)<flux:input name="owner" label="UUID du commerçant" :value="request('owner')" />@endunless
            <flux:select name="status" label="État">
                <option value="">Tous les états</option>
                @foreach (\App\Enums\Central\Billing\DocumentStatusEnum::cases() as $state)<option value="{{ $state->value }}" @selected((string) request('status') === (string) $state->value)>{{ $state->label() }}</option>@endforeach
            </flux:select>
            <flux:input type="date" name="period_start" label="Période à partir du" :value="request('period_start')" />
            <flux:input type="date" name="period_end" label="Période avant le" :value="request('period_end')" />
            <flux:button type="submit">Rechercher</flux:button>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3">Numéro</th><th class="p-3">Commerçant</th><th class="p-3">Abonnement</th><th class="p-3">Montant</th><th class="p-3">Date</th><th class="p-3">Période</th><th class="p-3">État du document</th><th class="p-3">Paiement</th><th class="p-3">Actions</th></tr></thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td class="p-3">{{ $invoice->number ?? 'Non réservé' }}</td><td class="p-3">{{ $invoice->owner?->name }}</td>
                            <td class="p-3">{{ $invoice->subscription?->plan?->name }}</td>
                            <td class="p-3">{{ $invoice->total_amount }} {{ $invoice->currency }}</td>
                            <td class="p-3">{{ ($invoice->issued_at ?? $invoice->created_at)?->format('d/m/Y') }}</td>
                            <td class="p-3">{{ $invoice->period_starts_at->format('d/m/Y') }} — {{ $invoice->period_ends_at->format('d/m/Y') }}</td>
                            <td class="p-3">{{ $invoice->status->label() }}</td>
                            <td class="p-3">{{ $paymentStates[$invoice->id]->label() }}</td>
                            <td class="p-3"><flux:button size="sm" :href="route($ownerMode ? 'owner.invoices.show' : 'central.invoices.show', $invoice)">Informations</flux:button></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="p-3">Aucune facture préparée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $invoices->links() }}
        @if ($ownerMode)
            <flux:button :href="route('owner.credit-notes.index')">Mes avoirs</flux:button>
            <flux:button :href="route('owner.subscription-requests.index')">Mes demandes et factures en préparation</flux:button>
        @else
            @can('viewAny', \App\Models\Central\Subscriptions\SubscriptionRequest::class)
                <flux:button :href="route('central.subscription-requests.index')">Demandes, y compris celles sans facture définitive</flux:button>
            @endcan
        @endif
    </section>
</x-layouts::app>
