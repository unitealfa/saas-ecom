<x-layouts::app :title="__('Déploiements')">
    <section class="mx-auto flex w-full max-w-screen-2xl flex-col gap-6">
        <header class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Déploiements des bases boutiques</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">Suivez la création et les mises à jour techniques. Les opérations sont exécutées par le worker central, sans créer de nouvelle boutique.</p></header>
        @if (session('status'))<flux:callout role="status">{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        @if ($canScheduleMigration)
            <section class="aydra-users-panel p-5 sm:p-6">
                <h2 class="aydra-heading text-lg font-semibold">Migrations automatiques</h2>
                @if ($migrationSchedule === null)
                    <p class="aydra-muted mt-2">Appliquez la migration de la base centrale pour configurer la programmation.</p>
                @else
                    <p class="aydra-muted mt-2">À l’heure choisie, les boutiques admissibles seront mises en file vers la dernière version. La migration manuelle reste disponible.</p>
                    <form method="POST" action="{{ route('central.deployments.schedule') }}" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf
                        <input type="hidden" name="version" value="{{ $migrationSchedule->version }}">
                        <flux:select name="enabled" label="Programmation">
                            <option value="0" @selected(! $migrationSchedule->enabled)>Désactivée</option>
                            <option value="1" @selected($migrationSchedule->enabled)>Activée</option>
                        </flux:select>
                        <flux:input type="time" name="run_time" label="Heure quotidienne" :value="old('run_time', $migrationSchedule->run_time)" />
                        <flux:select name="timezone" label="Fuseau horaire">
                            @foreach (timezone_identifiers_list() as $timezone)
                                <option value="{{ $timezone }}" @selected(old('timezone', $migrationSchedule->timezone ?? config('app.timezone')) === $timezone)>{{ $timezone }}</option>
                            @endforeach
                        </flux:select>
                        <flux:button type="submit" variant="primary">Enregistrer</flux:button>
                    </form>
                @endif
            </section>
        @endif
        <section class="aydra-users-panel p-5 sm:p-6" aria-labelledby="deployment-search"><h2 id="deployment-search" class="aydra-heading text-lg font-semibold">Rechercher un déploiement</h2><p class="aydra-muted mt-1 text-sm">Les suggestions proviennent des boutiques affichées sur cette page.</p>
            <form method="GET" action="{{ route('central.deployments.index') }}" class="mt-5 flex flex-wrap items-end gap-3">
                <flux:input name="search" :value="request('search')" label="Boutique" type="search" icon="magnifying-glass" placeholder="Nom de la boutique" list="deployment-suggestions" class="min-w-0 flex-1 sm:min-w-72" />
                <datalist id="deployment-suggestions">@foreach ($deployments as $deployment) @if ($deployment->tenant?->shop_name)<option value="{{ $deployment->tenant->shop_name }}"></option>@endif @endforeach</datalist>
                <div class="w-full sm:w-48" x-data="{ value: '', label: '' }" data-value="{{ request('status', '') }}" data-label="{{ \App\Enums\Central\Deployments\StatusEnum::tryFrom((int) request('status'))?->label() ?? 'Tous les états' }}" x-init="value = $el.dataset.value; label = $el.dataset.label"><label id="deployment-status-label" class="mb-2 block text-sm font-medium">État</label><input type="hidden" name="status" :value="value" value="{{ request('status', '') }}"><flux:dropdown><flux:button type="button" icon:trailing="chevron-down" class="w-full justify-between" aria-labelledby="deployment-status-label deployment-status-value"><span id="deployment-status-value" x-text="label">Tous les états</span></flux:button><flux:menu><flux:menu.item type="button" data-value="" @click="value = $el.dataset.value; label = $el.textContent.trim()">Tous les états</flux:menu.item>@foreach (\App\Enums\Central\Deployments\StatusEnum::cases() as $status)<flux:menu.item type="button" data-value="{{ $status->value }}" @click="value = $el.dataset.value; label = $el.textContent.trim()">{{ $status->label() }}</flux:menu.item>@endforeach</flux:menu></flux:dropdown></div>
                <div class="w-full sm:w-56" x-data="{ value: '', label: '' }" data-value="{{ request('operation', '') }}" data-label="{{ \App\Enums\Central\Deployments\OperationEnum::tryFrom((int) request('operation'))?->label() ?? 'Toutes les opérations' }}" x-init="value = $el.dataset.value; label = $el.dataset.label"><label id="deployment-operation-label" class="mb-2 block text-sm font-medium">Opération</label><input type="hidden" name="operation" :value="value" value="{{ request('operation', '') }}"><flux:dropdown><flux:button type="button" icon:trailing="chevron-down" class="w-full justify-between" aria-labelledby="deployment-operation-label deployment-operation-value"><span id="deployment-operation-value" x-text="label">Toutes les opérations</span></flux:button><flux:menu><flux:menu.item type="button" data-value="" @click="value = $el.dataset.value; label = $el.textContent.trim()">Toutes les opérations</flux:menu.item>@foreach (\App\Enums\Central\Deployments\OperationEnum::cases() as $operation)<flux:menu.item type="button" data-value="{{ $operation->value }}" @click="value = $el.dataset.value; label = $el.textContent.trim()">{{ $operation->label() }}</flux:menu.item>@endforeach</flux:menu></flux:dropdown></div>
                @if (request()->filled('sort'))<input type="hidden" name="sort" value="{{ request('sort') }}"><input type="hidden" name="direction" value="{{ request('direction', 'asc') }}">@endif
                <flux:button type="submit" variant="primary">Rechercher</flux:button><flux:button :href="route('central.deployments.index')">Effacer</flux:button>
            </form>
        </section>
        @php($sortBy = request('sort')) @php($sortDirection = request('direction', 'asc'))
        <section class="aydra-users-panel"><div class="aydra-users-toolbar"><div><h2 class="aydra-heading text-lg font-semibold">Déploiements</h2><p class="aydra-muted mt-1 text-sm">{{ $deployments->total() }} {{ $deployments->total() === 1 ? 'déploiement trouvé' : 'déploiements trouvés' }}</p></div></div>
            <div class="aydra-users-table"><flux:table class="whitespace-normal"><flux:table.columns>
                @foreach (['tenant' => 'Boutique', 'operation' => 'Opération', 'status' => 'État', 'attempt_number' => 'Tentative', 'target_version' => 'Version cible', 'created_at' => 'Demandé le', 'actions' => 'Actions'] as $column => $heading)
                    @if (in_array($column, ['operation', 'status', 'attempt_number', 'target_version', 'created_at'], true))<flux:table.column sortable :sorted="$sortBy === $column" :direction="$sortDirection" :aria-sort="$sortBy === $column ? ($sortDirection === 'asc' ? 'ascending' : 'descending') : 'none'" :data-sort-url="route('central.deployments.index', [...request()->only(['search', 'status', 'operation']), 'sort' => $column, 'direction' => $sortBy === $column && $sortDirection === 'asc' ? 'desc' : 'asc'])" onclick="window.location.assign(this.dataset.sortUrl)">{{ $heading }}</flux:table.column>@else <flux:table.column>{{ $heading }}</flux:table.column> @endif
                @endforeach
            </flux:table.columns><flux:table.rows>
                @forelse ($deployments as $deployment)
                    <flux:table.row :key="$deployment->uuid"><flux:table.cell variant="strong">{{ $deployment->tenant?->shop_name ?? 'Boutique indisponible' }} @if ($deployment->tenant?->trashed())<flux:badge size="sm">Archivée</flux:badge>@endif</flux:table.cell><flux:table.cell>{{ $deployment->operation->label() }}</flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="match ($deployment->status) { \App\Enums\Central\Deployments\StatusEnum::SUCCEEDED => 'green', \App\Enums\Central\Deployments\StatusEnum::FAILED => 'red', \App\Enums\Central\Deployments\StatusEnum::PENDING, \App\Enums\Central\Deployments\StatusEnum::RUNNING => 'amber', default => 'zinc' }">{{ $deployment->status->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell>{{ $deployment->attempt_number }}</flux:table.cell><flux:table.cell>{{ $deployment->target_version }}</flux:table.cell><flux:table.cell class="whitespace-nowrap">{{ $deployment->created_at?->format('d/m/Y H:i') ?? '—' }}</flux:table.cell><flux:table.cell><flux:dropdown position="bottom" align="end"><flux:button type="button" variant="ghost" size="sm" icon="bars-3" aria-label="Actions" title="Actions" /><flux:menu><flux:menu.item :href="route('central.deployments.show', $deployment)">Informations</flux:menu.item></flux:menu></flux:dropdown></flux:table.cell></flux:table.row>
                @empty <flux:table.row><flux:table.cell colspan="7"><div class="aydra-users-empty"><p class="aydra-heading font-medium">Aucun déploiement trouvé.</p><p class="aydra-muted mt-1 text-sm">Essayez d’autres critères de recherche.</p></div></flux:table.cell></flux:table.row> @endforelse
            </flux:table.rows></flux:table>
            @if ($deployments->hasPages())<nav aria-label="Pagination des déploiements" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700"><span class="aydra-muted text-sm">Page {{ $deployments->currentPage() }} sur {{ $deployments->lastPage() }}</span><div class="flex gap-2">@if ($deployments->onFirstPage())<flux:button disabled icon="chevron-left">Précédent</flux:button>@else<flux:button :href="$deployments->previousPageUrl()" icon="chevron-left">Précédent</flux:button>@endif @if ($deployments->hasMorePages())<flux:button :href="$deployments->nextPageUrl()" icon:trailing="chevron-right">Suivant</flux:button>@else<flux:button disabled icon:trailing="chevron-right">Suivant</flux:button>@endif</div></nav>@endif
            </div>
        </section>
    </section>
</x-layouts::app>
