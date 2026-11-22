<x-layouts::app :title="__('Abonnements')">
    <section class="mx-auto flex w-full max-w-screen-2xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5">
            <div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">{{ isset($requests) ? 'Demandes d’abonnement' : 'Abonnements' }}</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">{{ isset($requests) ? 'Retrouvez les demandes et suivez leur traitement.' : 'Retrouvez les abonnements et consultez leur période et leur état.' }}</p></div>
            @unless (isset($requests))
                @can('assign', \App\Models\Central\Subscriptions\Subscription::class) @can('saas.payments.validate')<flux:button :href="route('central.subscriptions.create')" variant="primary" icon="plus">Attribuer un abonnement</flux:button>@endcan @endcan
            @endunless
        </header>
        @if (session('status'))<flux:callout role="status">{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        @isset($requests)
            @include('central.pages.admin.subscriptions.requests.list', ['ownerMode' => false])
        @else
            <section class="aydra-users-panel p-5 sm:p-6" aria-labelledby="subscription-search">
                <h2 id="subscription-search" class="aydra-heading text-lg font-semibold">Rechercher un abonnement</h2>
                <p class="aydra-muted mt-1 text-sm">Les suggestions proviennent des abonnements affichés sur cette page.</p>
                <form method="GET" action="{{ route('central.subscriptions.index') }}" class="mt-5 flex flex-wrap items-end gap-3">
                    <flux:input name="search" label="Propriétaire, offre ou identifiant" :value="request('search')" type="search" icon="magnifying-glass" list="subscription-suggestions" placeholder="Nom, e-mail, offre ou UUID" class="min-w-0 flex-1 sm:min-w-72" />
                    <datalist id="subscription-suggestions">@foreach ($subscriptions as $subscription) @if($subscription->owner?->name)<option value="{{ $subscription->owner->name }}"></option>@endif @if($subscription->owner?->email)<option value="{{ $subscription->owner->email }}"></option>@endif @if($subscription->plan?->name)<option value="{{ $subscription->plan->name }}"></option>@endif <option value="{{ $subscription->uuid }}"></option> @endforeach</datalist>
                    <div class="w-full sm:w-56" x-data="{ value: '', label: '' }" data-value="{{ request('status', '') }}" data-label="{{ \App\Enums\Central\Subscriptions\StatusEnum::tryFrom((int) request('status'))?->label() ?? 'Tous les états' }}" x-init="value = $el.dataset.value; label = $el.dataset.label">
                        <label id="subscription-status-label" class="mb-2 block text-sm font-medium">État</label><input type="hidden" name="status" :value="value" value="{{ request('status', '') }}">
                        <flux:dropdown><flux:button type="button" icon:trailing="chevron-down" class="w-full justify-between" aria-labelledby="subscription-status-label subscription-status-value"><span id="subscription-status-value" x-text="label">Tous les états</span></flux:button><flux:menu>
                            <flux:menu.item type="button" data-value="" @click="value = $el.dataset.value; label = $el.textContent.trim()">Tous les états</flux:menu.item>
                            @foreach ($statuses as $status)<flux:menu.item type="button" data-value="{{ $status->value }}" @click="value = $el.dataset.value; label = $el.textContent.trim()">{{ $status->label() }}</flux:menu.item>@endforeach
                        </flux:menu></flux:dropdown>
                    </div>
                    @if (request()->filled('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="direction" value="{{ request('direction', 'asc') }}">@endif
                    <flux:button type="submit" variant="primary">Rechercher</flux:button><flux:button :href="route('central.subscriptions.index')">Effacer</flux:button>
                </form>
            </section>
            @php($sortBy = request('sort'))
            @php($sortDirection = request('direction', 'asc'))
            <section class="aydra-users-panel" aria-labelledby="subscription-list">
                <div class="aydra-users-toolbar"><div><h2 id="subscription-list" class="aydra-heading text-lg font-semibold">Abonnements</h2><p class="aydra-muted mt-1 text-sm">{{ $subscriptions->total() }} {{ $subscriptions->total() === 1 ? 'abonnement trouvé' : 'abonnements trouvés' }}</p></div></div>
                <div class="aydra-users-table"><flux:table class="whitespace-normal">
                    <flux:table.columns>
                        @foreach (['owner' => 'Propriétaire', 'plan' => 'Offre', 'status' => 'État', 'period_starts_at' => 'Période', 'agreed_amount' => 'Montant convenu', 'actions' => 'Actions'] as $column => $heading)
                            @if (in_array($column, ['status', 'period_starts_at', 'agreed_amount'], true))
                                <flux:table.column sortable :sorted="$sortBy === $column" :direction="$sortDirection" :aria-sort="$sortBy === $column ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none'" :data-sort-url="route('central.subscriptions.index', [...request()->only(['search', 'status']), 'sort' => $column, 'direction' => $sortBy === $column && $sortDirection === 'asc' ? 'desc' : 'asc'])" onclick="window.location.assign(this.dataset.sortUrl)">{{ $heading }}</flux:table.column>
                            @else <flux:table.column>{{ $heading }}</flux:table.column> @endif
                        @endforeach
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($subscriptions as $subscription)
                            <flux:table.row :key="$subscription->uuid">
                                <flux:table.cell variant="strong">{{ $subscription->owner?->name ?? 'Compte indisponible' }}<div class="aydra-muted mt-1 text-xs">{{ $subscription->owner?->email }}</div></flux:table.cell>
                                <flux:table.cell>{{ $subscription->plan?->name ?? 'Offre indisponible' }}@if ($subscription->plan) — v{{ $subscription->plan->version }}@endif</flux:table.cell>
                                <flux:table.cell><flux:badge size="sm">{{ $subscription->status?->label() ?? 'Non renseigné' }}</flux:badge></flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap">{{ $subscription->effectiveStartsAt()?->format('d/m/Y') }} — {{ $subscription->effectiveEndsAt()?->format('d/m/Y') ?? 'Sans échéance' }}</flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap tabular-nums">{{ $subscription->agreed_amount !== null ? $subscription->agreed_amount.' DZD' : 'Non renseigné' }}</flux:table.cell>
                                <flux:table.cell><flux:dropdown position="bottom" align="end"><flux:button type="button" variant="ghost" size="sm" icon="bars-3" aria-label="Actions" title="Actions" /><flux:menu><flux:menu.item :href="route('central.subscriptions.show', $subscription)">Détails</flux:menu.item>@can('history', $subscription)<flux:menu.item :href="route('central.subscriptions.history', $subscription)">Historique</flux:menu.item>@endcan</flux:menu></flux:dropdown></flux:table.cell>
                            </flux:table.row>
                        @empty <flux:table.row><flux:table.cell colspan="6"><div class="aydra-users-empty"><p class="aydra-heading font-medium">Aucun abonnement trouvé.</p><p class="aydra-muted mt-1 text-sm">Essayez d’autres critères de recherche.</p></div></flux:table.cell></flux:table.row> @endforelse
                    </flux:table.rows>
                </flux:table>
                @if ($subscriptions->hasPages())<nav aria-label="Pagination des abonnements" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700"><span class="aydra-muted text-sm">Page {{ $subscriptions->currentPage() }} sur {{ $subscriptions->lastPage() }}</span><div class="flex gap-2">@if ($subscriptions->onFirstPage())<flux:button disabled icon="chevron-left">Précédent</flux:button>@else<flux:button :href="$subscriptions->previousPageUrl()" icon="chevron-left">Précédent</flux:button>@endif @if ($subscriptions->hasMorePages())<flux:button :href="$subscriptions->nextPageUrl()" icon:trailing="chevron-right">Suivant</flux:button>@else<flux:button disabled icon:trailing="chevron-right">Suivant</flux:button>@endif</div></nav>@endif
                </div>
            </section>
        @endisset
    </section>
</x-layouts::app>
