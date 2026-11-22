<nav aria-label="Filtrer les demandes" class="flex flex-wrap items-center gap-2">
    @foreach (\App\Enums\Central\Subscriptions\RequestFilterEnum::cases() as $filter)
        <flux:button :href="route($ownerMode ? 'owner.subscription-requests.index' : 'central.subscription-requests.index', ['filter' => $filter->value, 'search' => request('search')])" :variant="request('filter', 'all') === $filter->value ? 'primary' : 'outline'">{{ $filter->label() }}</flux:button>
    @endforeach
</nav>
<section class="aydra-users-panel p-5 sm:p-6">
    <h2 class="aydra-heading text-lg font-semibold">Rechercher une demande</h2><p class="aydra-muted mt-1 text-sm">Les suggestions proviennent des demandes affichées sur cette page.</p>
    <form method="GET" class="mt-5 flex flex-wrap items-end gap-3">
        @if (request('filter'))<input type="hidden" name="filter" value="{{ request('filter') }}">@endif
        <flux:input name="search" label="Référence, nom du commerçant, e-mail ou facture" :value="request('search')" type="search" icon="magnifying-glass" list="subscription-request-suggestions" class="min-w-0 flex-1 sm:min-w-72" />
        <datalist id="subscription-request-suggestions">@foreach ($requests as $item)<option value="{{ $item->uuid }}"></option>@if($item->owner?->name)<option value="{{ $item->owner->name }}"></option>@endif @if($item->owner?->email)<option value="{{ $item->owner->email }}"></option>@endif @if($item->invoice?->number)<option value="{{ $item->invoice->number }}"></option>@endif @endforeach</datalist>
        <div class="w-full sm:w-56" x-data="{ value: '', label: '' }" data-value="{{ request('status', '') }}" data-label="{{ \App\Enums\Central\Subscriptions\RequestStatusEnum::tryFrom((int) request('status'))?->label() ?? 'Tous les états' }}" x-init="value = $el.dataset.value; label = $el.dataset.label">
            <label id="request-status-label" class="mb-2 block text-sm font-medium">État</label><input type="hidden" name="status" :value="value" value="{{ request('status', '') }}">
            <flux:dropdown><flux:button type="button" icon:trailing="chevron-down" class="w-full justify-between" aria-labelledby="request-status-label request-status-value"><span id="request-status-value" x-text="label">Tous les états</span></flux:button><flux:menu>
                <flux:menu.item type="button" data-value="" @click="value = $el.dataset.value; label = $el.textContent.trim()">Tous les états</flux:menu.item>
                @foreach (\App\Enums\Central\Subscriptions\RequestStatusEnum::cases() as $state) @continue($state === \App\Enums\Central\Subscriptions\RequestStatusEnum::INVOICE_PENDING)<flux:menu.item type="button" data-value="{{ $state->value }}" @click="value = $el.dataset.value; label = $el.textContent.trim()">{{ $state->label() }}</flux:menu.item> @endforeach
            </flux:menu></flux:dropdown>
        </div>
        <flux:button type="submit" variant="primary">Rechercher</flux:button><flux:button :href="route($ownerMode ? 'owner.subscription-requests.index' : 'central.subscription-requests.index')">Effacer</flux:button>
    </form>
</section>
<section class="aydra-users-panel"><div class="aydra-users-toolbar"><div><h2 class="aydra-heading text-lg font-semibold">Demandes</h2><p class="aydra-muted mt-1 text-sm">{{ $requests->total() }} {{ $requests->total() === 1 ? 'demande trouvée' : 'demandes trouvées' }}</p></div></div>
    <div class="aydra-users-table"><flux:table class="whitespace-normal"><flux:table.columns>@foreach (['Référence', 'Commerçant', 'Offre', 'Montant TTC', 'Méthode de paiement', 'Date de demande', 'Statut', 'Actions'] as $heading)<flux:table.column>{{ $heading }}</flux:table.column>@endforeach</flux:table.columns><flux:table.rows>
        @forelse ($requests as $item)
            <flux:table.row :key="$item->uuid">
                <flux:table.cell variant="strong" class="break-all">{{ $item->uuid }}</flux:table.cell>
                <flux:table.cell>{{ $item->owner?->name ?? 'Compte indisponible' }}<div class="aydra-muted mt-1 text-xs">{{ $item->owner?->email }}</div></flux:table.cell>
                <flux:table.cell>{{ $item->subscription?->plan?->name ?? 'Offre indisponible' }}</flux:table.cell>
                <flux:table.cell class="whitespace-nowrap tabular-nums">{{ $item->subscription?->agreed_amount !== null ? $item->subscription->agreed_amount.' DZD' : 'Non renseigné' }}</flux:table.cell>
                <flux:table.cell>{{ $item->subscription?->agreed_amount === '0.00' ? 'Aucun paiement requis' : $item->payment_choice->label() }}</flux:table.cell>
                <flux:table.cell class="whitespace-nowrap">{{ $item->submitted_at?->format('d/m/Y H:i') ?? '—' }}</flux:table.cell>
                <flux:table.cell><flux:badge size="sm" :color="match ($item->status) { \App\Enums\Central\Subscriptions\RequestStatusEnum::ACCEPTED => 'green', \App\Enums\Central\Subscriptions\RequestStatusEnum::REJECTED, \App\Enums\Central\Subscriptions\RequestStatusEnum::CANCELLED => 'red', \App\Enums\Central\Subscriptions\RequestStatusEnum::REVIEW => 'amber', default => 'zinc' }">{{ $item->statusLabel() }}</flux:badge></flux:table.cell>
                <flux:table.cell><flux:button :href="route($ownerMode ? 'owner.subscription-requests.show' : 'central.subscription-requests.show', $item)">Informations</flux:button></flux:table.cell>
            </flux:table.row>
        @empty <flux:table.row><flux:table.cell colspan="8"><div class="aydra-users-empty"><p class="aydra-heading font-medium">Aucune demande trouvée.</p><p class="aydra-muted mt-1 text-sm">Essayez d’autres critères de recherche.</p></div></flux:table.cell></flux:table.row> @endforelse
    </flux:table.rows></flux:table>
    @if ($requests->hasPages())<nav aria-label="Pagination des demandes" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700"><span class="aydra-muted text-sm">Page {{ $requests->currentPage() }} sur {{ $requests->lastPage() }}</span><div class="flex gap-2">@if ($requests->onFirstPage())<flux:button disabled icon="chevron-left">Précédent</flux:button>@else<flux:button :href="$requests->previousPageUrl()" icon="chevron-left">Précédent</flux:button>@endif @if ($requests->hasMorePages())<flux:button :href="$requests->nextPageUrl()" icon:trailing="chevron-right">Suivant</flux:button>@else<flux:button disabled icon:trailing="chevron-right">Suivant</flux:button>@endif</div></nav>@endif
    </div>
</section>
@if ($ownerMode)<div><flux:button :href="route('owner.subscriptions.index')">Choisir une offre</flux:button></div>@endif
