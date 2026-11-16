<x-layouts::app :title="$ownerMode ? 'Mes factures et avoirs' : 'Factures SaaS'">
    <section class="mx-auto flex w-full max-w-7xl flex-col gap-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-2">
                <flux:heading size="xl" level="1">{{ $ownerMode ? 'Mes factures et avoirs' : 'Factures SaaS' }}</flux:heading>
                <flux:text>Consultez les documents, leur période et leur état de paiement.</flux:text>
            </div>
            @can('viewAny', \App\Models\Central\Subscriptions\Subscription::class)
                <flux:button :href="route('central.subscriptions.index')">Choisir un abonnement à facturer</flux:button>
            @endcan
        </header>

        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach

        <form method="GET" class="rounded-xl border border-zinc-200 bg-white p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:input name="search" label="Numéro de facture" placeholder="Rechercher un numéro" :value="request('search')" />
                @unless ($ownerMode)
                    <flux:input name="owner" label="Commerçant" placeholder="Nom ou adresse e-mail" :value="request('owner')" />
                @endunless
                <flux:select name="filter" label="Paiement et corrections">
                    @foreach (\App\Enums\Central\Billing\InvoiceListFilterEnum::cases() as $filter)
                        <option value="{{ $filter->value }}" @selected(request('filter', 'all') === $filter->value)>{{ $filter->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:select name="status" label="État du document">
                    <option value="">Tous les états</option>
                    @foreach (\App\Enums\Central\Billing\DocumentStatusEnum::cases() as $state)
                        <option value="{{ $state->value }}" @selected((string) request('status') === (string) $state->value)>{{ $state->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input type="date" name="period_start" label="Période à partir du" :value="request('period_start')" />
                <flux:input type="date" name="period_end" label="Période avant le" :value="request('period_end')" />
            </div>
            <div class="mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button :href="route($ownerMode ? 'owner.invoices.index' : 'central.invoices.index')">Réinitialiser</flux:button>
                <flux:button type="submit" variant="primary">Rechercher</flux:button>
            </div>
        </form>

        <section aria-labelledby="invoices-heading" class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-200 px-4 py-4 sm:px-5 dark:border-zinc-700">
                <flux:heading id="invoices-heading" level="2">Factures</flux:heading>
                <flux:text>{{ $invoices->total() }} résultat(s)</flux:text>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                        <tr>
                            @foreach (['Facture', 'Commerçant', 'Offre et période', 'Montant TTC', 'Document', 'Paiement', 'Actions'] as $column)
                                <th scope="col" class="whitespace-nowrap px-4 py-3 font-medium">{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @forelse ($invoices as $invoice)
                            <tr class="align-top hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                <td class="px-4 py-4">
                                    <div class="whitespace-nowrap font-medium">{{ $invoice->number ?? 'Sans numéro' }}</div>
                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ ($invoice->issued_at ?? $invoice->created_at)?->format('d/m/Y') }}</div>
                                </td>
                                <td class="px-4 py-4">{{ $invoice->owner?->name ?? 'Compte indisponible' }}</td>
                                <td class="px-4 py-4">
                                    <div>{{ $invoice->subscription?->plan?->name ?? 'Offre historique' }}</div>
                                    <div class="mt-1 whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">{{ $invoice->period_starts_at->format('d/m/Y') }} — {{ $invoice->period_ends_at->format('d/m/Y') }}</div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 font-medium tabular-nums">{{ $invoice->total_amount }} {{ $invoice->currency }}</td>
                                <td class="px-4 py-4"><span class="inline-flex rounded-md bg-zinc-100 px-2 py-1 text-xs font-medium dark:bg-zinc-800">{{ $invoice->status->label() }}</span></td>
                                <td class="px-4 py-4">{{ $paymentStates[$invoice->id]->label() }}</td>
                                <td class="px-4 py-4"><flux:button size="sm" :href="route($ownerMode ? 'owner.invoices.show' : 'central.invoices.show', $invoice)">Consulter</flux:button></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">Aucune facture ne correspond à votre recherche.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">{{ $invoices->links() }}</div>
        </section>

        @if (! $ownerMode && $pendingInstallments !== null)
            <section aria-labelledby="installments-heading" class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                <div class="space-y-1 border-b border-zinc-200 px-4 py-4 sm:px-5 dark:border-zinc-700">
                    <flux:heading id="installments-heading" level="2">Échéances et paiements à traiter</flux:heading>
                    <flux:text>Les échéances en attente restent distinctes des factures émises.</flux:text>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 dark:bg-zinc-800"><tr>
                            @foreach (['Référence', 'Offre', 'Montant intégral', 'Dossier'] as $column)
                                <th scope="col" class="px-4 py-3 font-medium">{{ $column }}</th>
                            @endforeach
                        </tr></thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($pendingInstallments as $due)
                                <tr class="align-top">
                                    <td class="px-4 py-4">{{ $due->installment_number }}</td>
                                    <td class="px-4 py-4">{{ $due->subscription->plan->name }}</td>
                                    <td class="whitespace-nowrap px-4 py-4 tabular-nums">{{ $due->installment_amount }} DZD</td>
                                    <td class="px-4 py-4"><div class="flex flex-wrap gap-2">
                                        @if ($due->subscription->request)
                                            @can('view', $due->subscription->request)<flux:button size="sm" :href="route('central.subscription-requests.show', $due->subscription->request)">Contrôler la demande</flux:button>@endcan
                                        @endif
                                        @foreach ($due->invoices as $document)
                                            @can('viewBillingDossier', $document)<flux:button size="sm" :href="route('central.invoices.show', $document)">{{ $document->number ?? 'Préparation interne' }}</flux:button>@endcan
                                        @endforeach
                                    </div></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">Aucune échéance à traiter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-zinc-200 p-4 dark:border-zinc-700">{{ $pendingInstallments->links() }}</div>
            </section>
        @endif

        @if ($ownerMode)
            <div class="flex flex-wrap gap-2">
                <flux:button :href="route('owner.credit-notes.index')">Mes avoirs</flux:button>
                <flux:button :href="route('owner.subscription-requests.index')">Mes demandes et factures en préparation</flux:button>
            </div>
        @endif
    </section>
</x-layouts::app>
