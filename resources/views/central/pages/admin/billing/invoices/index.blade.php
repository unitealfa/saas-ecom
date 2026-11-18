<x-layouts::app :title="$ownerMode ? 'Mes factures et avoirs' : 'Factures SaaS'">
    <section class="mx-auto flex w-full max-w-screen-2xl flex-col gap-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-2">
                <h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">{{ $ownerMode ? 'Mes factures et avoirs' : 'Factures SaaS' }}</h1>
                <p class="aydra-muted max-w-2xl text-sm sm:text-base">Consultez les documents, leur période et leur état de paiement.</p>
            </div>
            @can('viewAny', \App\Models\Central\Subscriptions\Subscription::class)
                <flux:button :href="route('central.subscriptions.index')">Choisir un abonnement à facturer</flux:button>
            @endcan
        </header>

        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif

        <form method="GET" action="{{ route($ownerMode ? 'owner.invoices.index' : 'central.invoices.index') }}" class="aydra-users-panel aydra-activity-filters p-5 sm:p-6">
            <h2 class="aydra-heading text-lg font-semibold">Rechercher une facture</h2>
            <p class="aydra-muted mt-1 text-sm">Recherchez par numéro, commerçant ou période de facturation.</p>
            <div class="mt-5">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:input name="search" label="Numéro de facture" placeholder="Rechercher un numéro" :value="request('search')" list="invoice-number-suggestions" />
                @unless ($ownerMode)
                    <flux:input name="owner" label="Commerçant" placeholder="Nom ou adresse e-mail" :value="request('owner')" list="invoice-owner-suggestions" />
                @endunless
                <div x-data="activityDateRange" data-from="{{ request('period_start') }}" data-to="{{ request('period_end') }}" class="relative" @keydown.escape.window="open = false" @click.outside="open = false">
                    <label class="mb-2 block text-sm font-medium">Période de facturation</label>
                    <flux:button type="button" icon="calendar-days" class="w-full justify-start" @click="open = !open" x-bind:aria-expanded="open" aria-controls="invoice-calendar"><span x-text="label">Toutes les dates (UTC)</span></flux:button>
                    <div id="invoice-calendar" x-cloak x-show="open" class="absolute right-0 z-30 mt-2 w-80 max-w-[calc(100vw-3rem)] rounded-xl border border-zinc-200 bg-white p-4 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="mb-4 flex flex-wrap gap-2"><flux:button type="button" size="sm" @click="presetExclusive(1)">Aujourd’hui</flux:button><flux:button type="button" size="sm" @click="presetExclusive(7)">7 jours</flux:button><flux:button type="button" size="sm" @click="presetExclusive(30)">30 jours</flux:button></div>
                        <div class="mb-3 flex items-center justify-between"><button type="button" @click="moveMonth(-1)" aria-label="Mois précédent" class="rounded px-2 py-1 hover:bg-zinc-100 dark:hover:bg-zinc-800">‹</button><span class="text-sm font-semibold capitalize" x-text="title"></span><button type="button" @click="moveMonth(1)" aria-label="Mois suivant" class="rounded px-2 py-1 hover:bg-zinc-100 dark:hover:bg-zinc-800">›</button></div>
                        <div class="grid grid-cols-7 text-center text-xs text-zinc-500"><span>L</span><span>M</span><span>M</span><span>J</span><span>V</span><span>S</span><span>D</span></div>
                        <div class="mt-1 grid grid-cols-7 gap-y-1 text-center"><template x-for="(day, index) in days" :key="index"><div><template x-if="day"><button type="button" class="h-9 w-9 rounded-lg text-sm hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-offset-2 dark:hover:bg-zinc-800" :class="day.date === from || day.date === to ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : (from && to && day.date > from && day.date < to ? 'bg-zinc-100 dark:bg-zinc-800' : '')" :aria-label="day.date" :aria-pressed="day.date === from || day.date === to" @click="select(day.date)" x-text="day.day"></button></template></div></template></div>
                        <div class="mt-4 grid grid-cols-2 gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700"><flux:input name="period_start" type="date" label="À partir du" x-model="from" /><flux:input name="period_end" type="date" label="Avant le" x-model="to" /></div>
                        <p class="aydra-muted mt-2 text-xs">La date « Avant le » est exclue. Les deux dates sont nécessaires.</p>
                        <div class="mt-3 flex justify-between"><flux:button type="button" size="sm" @click="from = ''; to = ''">Effacer</flux:button><flux:button type="button" size="sm" @click="open = false">Terminé</flux:button></div>
                    </div>
                </div>
            </div>
            <details @if(request()->filled('status') || (request()->filled('filter') && request('filter') !== 'all')) open @endif class="aydra-animated-details mt-4 rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <summary class="cursor-pointer text-sm font-medium">Filtres avancés</summary>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div x-data="{ value: '', label: '' }" data-value="{{ request('filter', 'all') }}" data-label="{{ \App\Enums\Central\Billing\InvoiceListFilterEnum::tryFrom(request('filter', 'all'))?->label() ?? 'Toutes les factures' }}" x-init="value = $el.dataset.value; label = $el.dataset.label">
                        <label id="invoice-filter-label" class="mb-2 block text-sm font-medium">Paiement et corrections</label>
                        <input type="hidden" name="filter" :value="value" value="{{ request('filter', 'all') }}">
                        <flux:dropdown><flux:button type="button" icon:trailing="chevron-down" class="w-full justify-between" aria-labelledby="invoice-filter-label invoice-filter-value"><span id="invoice-filter-value" x-text="label">Toutes les factures</span></flux:button><flux:menu>
                            @foreach (\App\Enums\Central\Billing\InvoiceListFilterEnum::cases() as $filter)<flux:menu.item type="button" data-value="{{ $filter->value }}" @click="value = $el.dataset.value; label = $el.textContent.trim()">{{ $filter->label() }}</flux:menu.item>@endforeach
                        </flux:menu></flux:dropdown>
                    </div>
                    <div x-data="{ value: '', label: '' }" data-value="{{ request('status', '') }}" data-label="{{ \App\Enums\Central\Billing\DocumentStatusEnum::tryFrom((int) request('status'))?->label() ?? 'Tous les états' }}" x-init="value = $el.dataset.value; label = $el.dataset.label">
                        <label id="invoice-status-label" class="mb-2 block text-sm font-medium">État du document</label>
                        <input type="hidden" name="status" :value="value" value="{{ request('status', '') }}">
                        <flux:dropdown><flux:button type="button" icon:trailing="chevron-down" class="w-full justify-between" aria-labelledby="invoice-status-label invoice-status-value"><span id="invoice-status-value" x-text="label">Tous les états</span></flux:button><flux:menu>
                            <flux:menu.item type="button" data-value="" @click="value = $el.dataset.value; label = $el.textContent.trim()">Tous les états</flux:menu.item>
                            @foreach (\App\Enums\Central\Billing\DocumentStatusEnum::cases() as $state)<flux:menu.item type="button" data-value="{{ $state->value }}" @click="value = $el.dataset.value; label = $el.textContent.trim()">{{ $state->label() }}</flux:menu.item>@endforeach
                        </flux:menu></flux:dropdown>
                    </div>
                </div>
            </details>
            <datalist id="invoice-number-suggestions">@foreach ($invoices->getCollection()->pluck('number')->filter()->unique() as $number)<option value="{{ $number }}"></option>@endforeach</datalist>
            @unless ($ownerMode)<datalist id="invoice-owner-suggestions">@foreach ($invoices->getCollection()->pluck('owner.name')->filter()->unique() as $name)<option value="{{ $name }}"></option>@endforeach</datalist>@endunless
            <p class="aydra-muted mt-3 text-xs">Les suggestions proviennent des factures affichées sur cette page.</p>
            <div class="mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <flux:button :href="route($ownerMode ? 'owner.invoices.index' : 'central.invoices.index')">Réinitialiser</flux:button>
                <flux:button type="submit" variant="primary">Rechercher</flux:button>
            </div>
            @php($activeFilters = array_filter(request()->only(['search', 'owner', 'filter', 'status', 'period_start', 'period_end']), fn ($value, $key) => is_string($value) && $value !== '' && ! ($key === 'filter' && $value === 'all'), ARRAY_FILTER_USE_BOTH))
            @if ($activeFilters)
                <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <span class="aydra-muted text-sm">Filtres actifs :</span>
                    @foreach (['search' => 'Facture', 'owner' => 'Commerçant', 'filter' => 'Paiement et corrections', 'status' => 'État', 'period_start' => 'À partir du', 'period_end' => 'Avant le'] as $field => $label)
                        @if (isset($activeFilters[$field]))
                            @php($remaining = array_diff_key($activeFilters, in_array($field, ['period_start', 'period_end'], true) ? ['period_start' => true, 'period_end' => true] : [$field => true]))
                            <a class="rounded-full bg-zinc-100 px-3 py-1 text-sm hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700" href="{{ route($ownerMode ? 'owner.invoices.index' : 'central.invoices.index', $remaining) }}" aria-label="Retirer le filtre {{ $label }}">{{ $label }} : {{ match ($field) { 'filter' => \App\Enums\Central\Billing\InvoiceListFilterEnum::tryFrom($activeFilters[$field])?->label() ?? $activeFilters[$field], 'status' => \App\Enums\Central\Billing\DocumentStatusEnum::tryFrom((int) $activeFilters[$field])?->label() ?? $activeFilters[$field], default => $activeFilters[$field] } }} &times;</a>
                        @endif
                    @endforeach
                </div>
            @endif
            </div>
        </form>

        <section aria-labelledby="invoices-heading" class="aydra-users-panel">
            <div class="aydra-users-toolbar"><div><h2 id="invoices-heading" class="aydra-heading text-lg font-semibold">Factures</h2><p class="aydra-muted mt-1 text-sm">{{ $invoices->total() }} {{ $invoices->total() === 1 ? 'facture trouvée' : 'factures trouvées' }}</p></div></div>
            <div class="aydra-users-table">
                <flux:table class="whitespace-normal">
                    <flux:table.columns>@foreach (['Facture', 'Commerçant', 'Offre et période', 'Montant TTC', 'Document', 'Paiement', 'Actions'] as $column)<flux:table.column>{{ $column }}</flux:table.column>@endforeach</flux:table.columns>
                    <flux:table.rows>
                        @forelse ($invoices as $invoice)
                            <flux:table.row :key="$invoice->uuid">
                                <flux:table.cell variant="strong">
                                    <div class="whitespace-nowrap font-medium">{{ $invoice->number ?? 'Sans numéro' }}</div>
                                    <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ ($invoice->issued_at ?? $invoice->created_at)?->format('d/m/Y') }}</div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $invoice->owner?->name ?? 'Compte indisponible' }}</flux:table.cell>
                                <flux:table.cell>
                                    <div>{{ $invoice->subscription?->plan?->name ?? 'Offre historique' }}</div>
                                    <div class="mt-1 whitespace-nowrap text-xs text-zinc-500 dark:text-zinc-400">{{ $invoice->period_starts_at->format('d/m/Y') }} — {{ $invoice->period_ends_at->format('d/m/Y') }}</div>
                                </flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap tabular-nums">{{ $invoice->total_amount }} {{ $invoice->currency }}</flux:table.cell>
                                <flux:table.cell><flux:badge size="sm">{{ $invoice->status->label() }}</flux:badge></flux:table.cell>
                                <flux:table.cell>{{ $paymentStates[$invoice->id]->label() }}</flux:table.cell>
                                <flux:table.cell><flux:button size="sm" :href="route($ownerMode ? 'owner.invoices.show' : 'central.invoices.show', $invoice)">Consulter</flux:button></flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="7"><div class="aydra-users-empty"><p class="aydra-heading font-medium">Aucune facture ne correspond à votre recherche.</p><p class="aydra-muted mt-1 text-sm">Essayez d’autres critères de recherche.</p></div></flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
                @if ($invoices->hasPages())<nav aria-label="Pagination des factures" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700"><span class="aydra-muted text-sm">Page {{ $invoices->currentPage() }} sur {{ $invoices->lastPage() }}</span><div class="flex gap-2">@if ($invoices->onFirstPage())<flux:button disabled icon="chevron-left">Précédent</flux:button>@else<flux:button :href="$invoices->previousPageUrl()" icon="chevron-left">Précédent</flux:button>@endif @if ($invoices->hasMorePages())<flux:button :href="$invoices->nextPageUrl()" icon:trailing="chevron-right">Suivant</flux:button>@else<flux:button disabled icon:trailing="chevron-right">Suivant</flux:button>@endif</div></nav>@endif
            </div>
        </section>

        @if (! $ownerMode && $pendingInstallments !== null)
            <section aria-labelledby="installments-heading" class="aydra-users-panel">
                <div class="aydra-users-toolbar"><div>
                    <h2 id="installments-heading" class="aydra-heading text-lg font-semibold">Échéances et paiements à traiter</h2>
                    <p class="aydra-muted mt-1 text-sm">Les échéances en attente restent distinctes des factures émises.</p>
                </div>
                </div>
                <div class="aydra-users-table">
                    <flux:table class="whitespace-normal">
                        <flux:table.columns>@foreach (['Référence', 'Offre', 'Montant intégral', 'Dossier'] as $column)<flux:table.column>{{ $column }}</flux:table.column>@endforeach</flux:table.columns>
                        <flux:table.rows>
                            @forelse ($pendingInstallments as $due)
                                <flux:table.row :key="$due->uuid">
                                    <flux:table.cell variant="strong">{{ $due->installment_number }}</flux:table.cell>
                                    <flux:table.cell>{{ $due->subscription->plan->name }}</flux:table.cell>
                                    <flux:table.cell class="whitespace-nowrap tabular-nums">{{ $due->installment_amount }} DZD</flux:table.cell>
                                    <flux:table.cell><div class="flex flex-wrap gap-2">
                                        @if ($due->subscription->request)
                                            @can('view', $due->subscription->request)<flux:button size="sm" :href="route('central.subscription-requests.show', $due->subscription->request)">Contrôler la demande</flux:button>@endcan
                                        @endif
                                        @foreach ($due->invoices as $document)
                                            @can('viewBillingDossier', $document)<flux:button size="sm" :href="route('central.invoices.show', $document)">{{ $document->number ?? 'Préparation interne' }}</flux:button>@endcan
                                        @endforeach
                                    </div></flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row><flux:table.cell colspan="4"><div class="aydra-users-empty">Aucune échéance à traiter.</div></flux:table.cell></flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                    @if ($pendingInstallments->hasPages())<div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">{{ $pendingInstallments->links() }}</div>@endif
                </div>
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
