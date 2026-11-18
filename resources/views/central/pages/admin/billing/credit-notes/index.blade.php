<x-layouts::app :title="$ownerMode ? 'Mes avoirs' : 'Avoirs SaaS'">
    <section class="mx-auto flex w-full max-w-screen-2xl flex-col gap-6">
        <header class="space-y-2">
            <h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">{{ $ownerMode ? 'Mes avoirs' : 'Avoirs SaaS' }}</h1>
            <p class="aydra-muted max-w-2xl text-sm sm:text-base">Un avoir corrige une facture émise. Il ne constitue pas un remboursement.</p>
        </header>

        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif

        <section class="aydra-users-panel p-5 sm:p-6" aria-labelledby="credit-search">
            <h2 id="credit-search" class="aydra-heading text-lg font-semibold">Rechercher un avoir</h2>
            <p class="aydra-muted mt-1 text-sm">Les suggestions proviennent des avoirs affichés sur cette page.</p>
            <form method="GET" action="{{ route($ownerMode ? 'owner.credit-notes.index' : 'central.credit-notes.index') }}" class="mt-5 flex flex-wrap items-end gap-3">
                <flux:input name="search" label="Numéro d’avoir, facture ou UUID public" :value="request('search')" type="search" icon="magnifying-glass" list="credit-note-suggestions" class="min-w-0 flex-1 sm:min-w-72" />
                <datalist id="credit-note-suggestions">
                    @foreach ($credits as $credit)
                        @if ($credit->number)<option value="{{ $credit->number }}"></option>@endif
                        @if ($credit->originalInvoice?->number)<option value="{{ $credit->originalInvoice->number }}"></option>@endif
                        <option value="{{ $credit->uuid }}"></option>
                    @endforeach
                </datalist>
                @unless ($ownerMode)
                    <div class="w-full sm:w-56" x-data="{ value: '', label: '' }" data-value="{{ request('status', '') }}" data-label="{{ \App\Enums\Central\Billing\DocumentStatusEnum::tryFrom((int) request('status'))?->label() ?? 'Tous les états' }}" x-init="value = $el.dataset.value; label = $el.dataset.label">
                        <label id="credit-status-label" class="mb-2 block text-sm font-medium">État</label>
                        <input type="hidden" name="status" :value="value" value="{{ request('status', '') }}">
                        <flux:dropdown>
                            <flux:button type="button" icon:trailing="chevron-down" class="w-full justify-between" aria-labelledby="credit-status-label credit-status-value"><span id="credit-status-value" x-text="label">Tous les états</span></flux:button>
                            <flux:menu>
                                <flux:menu.item type="button" data-value="" @click="value = $el.dataset.value; label = $el.textContent.trim()">Tous les états</flux:menu.item>
                                @foreach (\App\Enums\Central\Billing\DocumentStatusEnum::cases() as $status)
                                    <flux:menu.item type="button" data-value="{{ $status->value }}" @click="value = $el.dataset.value; label = $el.textContent.trim()">{{ $status->label() }}</flux:menu.item>
                                @endforeach
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                @endunless
                @if (request()->filled('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="direction" value="{{ request('direction', 'asc') }}">@endif
                <flux:button type="submit" variant="primary">Rechercher</flux:button>
                <flux:button :href="route($ownerMode ? 'owner.credit-notes.index' : 'central.credit-notes.index')">Effacer</flux:button>
            </form>
        </section>

        @php
            $sortBy = request('sort');
            $sortDirection = request('direction', 'asc');
            $sortFilters = request()->only(['search', 'status']);
            $indexRoute = $ownerMode ? 'owner.credit-notes.index' : 'central.credit-notes.index';
        @endphp
        <section class="aydra-users-panel" aria-labelledby="credits-heading">
            <div class="aydra-users-toolbar"><div><h2 id="credits-heading" class="aydra-heading text-lg font-semibold">Avoirs</h2><p class="aydra-muted mt-1 text-sm">{{ $credits->total() }} {{ $credits->total() === 1 ? 'avoir trouvé' : 'avoirs trouvés' }}</p></div></div>
            <div class="aydra-users-table">
                <flux:table class="whitespace-normal">
                    <flux:table.columns>
                        @foreach (['number' => 'Avoir', 'original_invoice' => 'Facture corrigée', 'owner' => 'Commerçant', 'total_amount' => 'Correction TTC', 'status' => 'État', 'actions' => 'Actions'] as $column => $label)
                            @if (in_array($column, ['number', 'total_amount', 'status'], true))
                                <flux:table.column sortable :sorted="$sortBy === $column" :direction="$sortDirection" :aria-sort="$sortBy === $column ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none'" :data-sort-url="route($indexRoute, [...$sortFilters, 'sort' => $column, 'direction' => $sortBy === $column && $sortDirection === 'asc' ? 'desc' : 'asc'])" onclick="window.location.assign(this.dataset.sortUrl)">{{ $label }}</flux:table.column>
                            @else
                                <flux:table.column>{{ $label }}</flux:table.column>
                            @endif
                        @endforeach
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($credits as $credit)
                            <flux:table.row :key="$credit->uuid">
                                <flux:table.cell variant="strong">{{ $credit->number ?? 'Brouillon' }}</flux:table.cell>
                                <flux:table.cell>{{ $credit->originalInvoice->number }}</flux:table.cell>
                                <flux:table.cell>{{ $credit->owner?->name ?? 'Compte indisponible' }}</flux:table.cell>
                                <flux:table.cell class="whitespace-nowrap tabular-nums">{{ $credit->total_amount }} {{ $credit->currency }}</flux:table.cell>
                                <flux:table.cell><flux:badge size="sm">{{ $credit->status->label() }}</flux:badge></flux:table.cell>
                                <flux:table.cell><flux:button size="sm" :href="route($ownerMode ? 'owner.credit-notes.show' : 'central.credit-notes.show', $credit)">Informations</flux:button></flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="6"><div class="aydra-users-empty"><p class="aydra-heading font-medium">Aucun avoir disponible.</p><p class="aydra-muted mt-1 text-sm">Essayez d’autres critères de recherche.</p></div></flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
                @if ($credits->hasPages())
                    <nav aria-label="Pagination des avoirs" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <span class="aydra-muted text-sm">Page {{ $credits->currentPage() }} sur {{ $credits->lastPage() }}</span>
                        <div class="flex gap-2">@if ($credits->onFirstPage())<flux:button disabled icon="chevron-left">Précédent</flux:button>@else<flux:button :href="$credits->previousPageUrl()" icon="chevron-left">Précédent</flux:button>@endif @if ($credits->hasMorePages())<flux:button :href="$credits->nextPageUrl()" icon:trailing="chevron-right">Suivant</flux:button>@else<flux:button disabled icon:trailing="chevron-right">Suivant</flux:button>@endif</div>
                    </nav>
                @endif
            </div>
        </section>
    </section>
</x-layouts::app>
