<x-layouts::app :title="__('Domaines')">
    <section class="mx-auto flex w-full max-w-screen-2xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Domaines des boutiques</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">Chaque domaine appartient à une seule boutique. Un domaine archivé reste réservé et ne permet plus d’accéder à la boutique.</p></div>
            @can('create', \App\Models\Central\Domains\Domain::class)<flux:button :href="route('central.domains.create')" variant="primary" icon="plus">Ajouter un domaine</flux:button>@endcan
        </header>
        @include('central.pages.admin.domains.messages')
        <section class="aydra-users-panel p-5 sm:p-6" aria-labelledby="domain-search"><h2 id="domain-search" class="aydra-heading text-lg font-semibold">Rechercher un domaine</h2><p class="aydra-muted mt-1 text-sm">Les suggestions proviennent des domaines affichés sur cette page.</p>
            <form method="GET" action="{{ route('central.domains.index') }}" class="mt-5 flex flex-wrap items-end gap-3">
                <flux:input name="search" :value="request('search')" label="Domaine ou nom de boutique" type="search" icon="magnifying-glass" list="domain-suggestions" class="min-w-0 flex-1 sm:min-w-72" />
                <datalist id="domain-suggestions">@foreach ($domains as $domain)<option value="{{ $domain->domain }}"></option>@if ($domain->tenant?->shop_name)<option value="{{ $domain->tenant->shop_name }}"></option>@endif @endforeach</datalist>
                <div class="w-full sm:w-56" x-data="{ value: '', label: '' }" data-value="{{ request()->boolean('archived') ? '1' : '0' }}" data-label="{{ request()->boolean('archived') ? 'Domaines archivés' : 'Domaines actuels' }}" x-init="value = $el.dataset.value; label = $el.dataset.label"><label id="domain-state-label" class="mb-2 block text-sm font-medium">État</label><input type="hidden" name="archived" :value="value" value="{{ request()->boolean('archived') ? '1' : '0' }}"><flux:dropdown><flux:button type="button" icon:trailing="chevron-down" class="w-full justify-between" aria-labelledby="domain-state-label domain-state-value"><span id="domain-state-value" x-text="label">Domaines actuels</span></flux:button><flux:menu><flux:menu.item type="button" data-value="0" @click="value = $el.dataset.value; label = $el.textContent.trim()">Domaines actuels</flux:menu.item><flux:menu.item type="button" data-value="1" @click="value = $el.dataset.value; label = $el.textContent.trim()">Domaines archivés</flux:menu.item></flux:menu></flux:dropdown></div>
                @if(request()->filled('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="direction" value="{{ request('direction', 'asc') }}">@endif
                <flux:button type="submit" variant="primary">Rechercher</flux:button><flux:button :href="route('central.domains.index')">Effacer</flux:button>
            </form>
        </section>
        @php($sortBy = request('sort', 'domain')) @php($sortDirection = request('direction', 'asc'))
        <section class="aydra-users-panel"><div class="aydra-users-toolbar"><div><h2 class="aydra-heading text-lg font-semibold">Domaines</h2><p class="aydra-muted mt-1 text-sm">{{ $domains->total() }} {{ $domains->total() === 1 ? 'domaine trouvé' : 'domaines trouvés' }}</p></div></div>
        <div class="aydra-users-table"><flux:table class="whitespace-normal">
                <flux:table.columns>
                    @foreach (['domain' => 'Domaine', 'tenant' => 'Boutique', 'type' => 'Type', 'verification_status' => 'DNS', 'certificate_status' => 'HTTPS (dernier état connu)', 'actions' => 'Actions'] as $column => $heading)
                        @if (in_array($column, ['domain', 'type', 'verification_status', 'certificate_status'], true))<flux:table.column sortable :sorted="$sortBy === $column" :direction="$sortDirection" :aria-sort="$sortBy === $column ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none'" :data-sort-url="route('central.domains.index', [...request()->only(['search', 'archived']), 'sort' => $column, 'direction' => $sortBy === $column && $sortDirection === 'asc' ? 'desc' : 'asc'])" onclick="window.location.assign(this.dataset.sortUrl)">{{ $heading }}</flux:table.column>@else <flux:table.column>{{ $heading }}</flux:table.column> @endif
                    @endforeach
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($domains as $domain)
                        <flux:table.row :key="$domain->uuid">
                            <flux:table.cell variant="strong">
                                {{ $domain->domain }}
                                @if ($domain->is_primary)<flux:badge>Principal</flux:badge>@endif
                                @if ($domain->trashed())<flux:badge>Archivé</flux:badge>@endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $domain->tenant?->shop_name ?? 'Boutique indisponible' }}</flux:table.cell>
                            <flux:table.cell>{{ $domain->type === \App\Enums\Central\Domains\TypeEnum::SUBDOMAIN ? 'Sous-domaine Aydra' : 'Personnalisé' }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm">{{ match ($domain->verification_status) {
                                \App\Enums\VerificationStatusEnum::VERIFIED => 'Accepté',
                                \App\Enums\VerificationStatusEnum::FAILED => 'Échec',
                                \App\Enums\VerificationStatusEnum::EXPIRED => 'Expiré',
                                default => 'En attente',
                            } }}</flux:badge></flux:table.cell>
                            <flux:table.cell><flux:badge size="sm">{{ match ($domain->certificate_status) {
                                \App\Enums\Central\Domains\CertificateStatusEnum::ACTIVE => 'Valide',
                                \App\Enums\Central\Domains\CertificateStatusEnum::ERROR => 'Erreur',
                                \App\Enums\Central\Domains\CertificateStatusEnum::EXPIRED => 'Expiré',
                                \App\Enums\Central\Domains\CertificateStatusEnum::PENDING => 'En attente',
                                default => 'Non contrôlé',
                            } }}</flux:badge></flux:table.cell>
                            <flux:table.cell>
                                @if (! $domain->trashed() && $domain->tenant !== null && ! $domain->tenant->trashed())
                                    <div class="flex flex-wrap gap-2">
                                        @can('update', $domain)
                                            <flux:button :href="route('central.domains.edit', $domain)">Modifier</flux:button>
                                        @endcan
                                        @can('verify', $domain)
                                            @if ($domain->verification_status !== \App\Enums\VerificationStatusEnum::VERIFIED)
                                            <form method="POST" action="{{ route('central.domains.verify', $domain) }}">
                                                @csrf
                                                <input type="hidden" name="expected_state" value="{{ $domain->managementState() }}">
                                                <flux:button type="submit">Accepter</flux:button>
                                            </form>
                                            @endif
                                        @endcan
                                        @if (! $domain->is_primary)
                                            @can('setPrimary', $domain)
                                                <form method="POST" action="{{ route('central.domains.primary', $domain) }}">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="expected_state" value="{{ $domain->managementState() }}">
                                                    <input type="hidden" name="expected_primary_uuid" value="{{ $primaryDomains[$domain->tenant_id] ?? '' }}">
                                                    <flux:button type="submit">Définir comme principal</flux:button>
                                                </form>
                                            @endcan
                                            @can('archive', $domain)
                                                <form method="POST" action="{{ route('central.domains.destroy', $domain) }}" x-data x-on:submit="if (! window.confirm('Archiver ce domaine ? Il ne donnera plus accès à la boutique.')) $event.preventDefault()">
                                                    @csrf @method('DELETE')
                                                    <input type="hidden" name="expected_state" value="{{ $domain->managementState() }}">
                                                    <flux:button type="submit" variant="danger">Archiver</flux:button>
                                                </form>
                                            @endcan
                                        @endif
                                    </div>
                                    @can('verify', $domain)
                                        @if ($domain->type === \App\Enums\Central\Domains\TypeEnum::CUSTOM)
                                            <details class="aydra-animated-details mt-3">
                                                <summary class="cursor-pointer">Configuration DNS</summary>
                                                @include('central.pages.admin.domains.dns', ['verificationToken' => $verificationTokens[$domain->uuid]])
                                            </details>
                                        @endif
                                    @endcan
                                @elseif ($domain->trashed())
                                    @can('restore', $domain)
                                        <form method="POST" action="{{ route('central.domains.restore', $domain) }}">
                                            @csrf
                                            <input type="hidden" name="expected_state" value="{{ $domain->managementState() }}">
                                            <flux:button type="submit">Restaurer et vérifier à nouveau</flux:button>
                                        </form>
                                    @endcan
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="6"><div class="aydra-users-empty"><p class="aydra-heading font-medium">Aucun domaine trouvé.</p><p class="aydra-muted mt-1 text-sm">Essayez d’autres critères de recherche.</p></div></flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
            @if ($domains->hasPages())<nav aria-label="Pagination des domaines" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700"><span class="aydra-muted text-sm">Page {{ $domains->currentPage() }} sur {{ $domains->lastPage() }}</span><div class="flex gap-2">@if ($domains->onFirstPage())<flux:button disabled icon="chevron-left">Précédent</flux:button>@else<flux:button :href="$domains->previousPageUrl()" icon="chevron-left">Précédent</flux:button>@endif @if ($domains->hasMorePages())<flux:button :href="$domains->nextPageUrl()" icon:trailing="chevron-right">Suivant</flux:button>@else<flux:button disabled icon:trailing="chevron-right">Suivant</flux:button>@endif</div></nav>@endif
        </div></section>
    </section>
</x-layouts::app>
