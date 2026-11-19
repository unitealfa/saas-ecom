<x-layouts::app :title="$plan->name">
    <section class="mx-auto flex w-full max-w-screen-xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="min-w-0 space-y-2"><h1 class="aydra-heading break-words text-3xl font-semibold tracking-tight sm:text-4xl">{{ $plan->name }}</h1><div class="flex flex-wrap items-center gap-2"><span class="aydra-muted text-sm">Version {{ $plan->version }} · {{ $plan->code }}</span><flux:badge size="sm" :color="$plan->trashed() ? 'zinc' : ($plan->is_active ? 'green' : 'amber')">{{ $plan->trashed() ? 'Archivée' : ($plan->is_active ? 'Active' : 'Inactive') }}</flux:badge>@if($plan->is_default)<flux:badge size="sm">Plan par défaut</flux:badge>@endif</div></div><flux:button :href="route('central.plan-pages.index')" icon="arrow-left">Retour aux plans</flux:button></header>
        @if (session('status'))
            <flux:callout variant="success" icon="check-circle" role="status">
                {{ session('status') }}
            </flux:callout>
        @endif
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        @if ($plan->is_default)<flux:callout>Plan par défaut : attribution automatique gratuite aux nouveaux commerçants et à l’expiration des autres périodes.</flux:callout>@endif
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading text-lg font-semibold">Informations du plan</h2><p class="aydra-muted mt-1 text-sm">Prix et état de commercialisation de cette version.</p><dl class="aydra-detail-grid mt-6 grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
            <div><dt>Code permanent</dt><dd>{{ $plan->code }}</dd></div>
            <div><dt>Commercialisation</dt><dd>{{ $plan->trashed() ? 'Archivée' : ($plan->is_active ? 'Active' : 'Inactive') }}</dd></div>
            <div><dt>Prix mensuel</dt><dd>{{ $plan->monthly_price }} DZD</dd></div>
            <div><dt>Prix annuel</dt><dd>{{ $plan->annual_price }} DZD</dd></div>
            <div><dt>Description</dt><dd>{{ $plan->description ?: 'Non renseignée' }}</dd></div>
        </dl></section>
        @php($state = $plan->compositionState($plan->features))
        <section class="aydra-users-panel p-5 sm:p-6">
            <h2 class="aydra-heading text-lg font-semibold">Fonctionnalités et quotas</h2>
            <ul class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($plan->features as $item)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3"><div><p class="aydra-heading text-sm font-medium">{{ $item->feature->name }}</p><p class="aydra-muted mt-1 text-xs">{{ $item->feature->code }}</p></div><div class="flex flex-wrap items-center gap-2"><flux:badge size="sm" :color="$item->is_active ? 'green' : 'zinc'">{{ $item->is_active ? 'Incluse' : 'Désactivée' }}</flux:badge>@if ($item->limit !== null)<span class="aydra-muted text-sm">Limite {{ $item->limit }} {{ $item->feature->unit }}</span>@endif</div></li>
                @empty
                    <li class="aydra-muted py-3 text-sm">Aucune fonctionnalité configurée.</li>
                @endforelse
            </ul>
        </section>
        {{-- L'offre gratuite reste protégée : seul ce formulaire versionne ses quotas équipe. --}}
        @if ($plan->is_active && $plan->version === $latestVersion)
            @can('configureTeamQuotas', $plan)
                @php($rolesLimit = $plan->features->first(fn ($item) => $item->feature->code === \App\Models\Central\Features\Feature::TEAM_CUSTOM_ROLES_CODE)?->limit)
                @php($membersLimit = $plan->features->first(fn ($item) => $item->feature->code === \App\Models\Central\Features\Feature::TEAM_MEMBERS_CODE)?->limit)
                <details class="aydra-users-panel aydra-animated-details p-5 sm:p-6">
                    <summary class="aydra-heading cursor-pointer text-lg font-semibold">Configurer les quotas d'équipe de l'offre gratuite</summary>
                    <flux:text>Cette action prépare une nouvelle version gratuite inactive. L'activation est séparée ; les abonnements existants et le quota de boutiques sont conservés. Aucun nombre n'est prérempli si le quota n'existe pas.</flux:text>
                    <form method="POST" action="{{ route('central.plan-pages.free-team-quotas.update', $plan) }}" class="mt-4 flex max-w-xl flex-col gap-4">
                        @csrf
                        <input type="hidden" name="expected_state" value="{{ $state }}">
                        <input type="hidden" name="expected_latest_version" value="{{ $latestVersion }}">
                        <flux:input type="number" name="max_roles" min="0" max="2147483647" step="1" required label="Nombre maximal de rôles personnalisés par boutique" :value="old('max_roles', $rolesLimit)" />
                        <flux:input type="number" name="max_members" min="0" max="2147483647" step="1" required label="Nombre maximal de membres par boutique" :value="old('max_members', $membersLimit)" />
                        <flux:button type="submit" variant="primary" class="max-w-full self-start whitespace-normal">Préparer la version gratuite avec ces quotas</flux:button>
                    </form>
                </details>
            @endcan
        @endif
        @if (! $plan->is_active && $plan->version === $latestVersion)
            @can('activateTeamQuotaVersion', $plan)
                <form method="POST" action="{{ route('central.plan-pages.free-team-quotas.activate', $plan) }}" class="aydra-users-panel flex flex-wrap items-center gap-4 p-5 sm:p-6">
                    @csrf @method('PATCH')
                    <input type="hidden" name="expected_state" value="{{ $state }}">
                    <flux:text>Cette activation distincte commercialise la dernière version gratuite des quotas équipe.</flux:text>
                    <flux:button type="submit" variant="primary">Activer cette version gratuite</flux:button>
                </form>
            @endcan
        @endif
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading mb-4 text-lg font-semibold">Actions du plan</h2><div class="flex flex-wrap gap-3">
            @canany(['update', 'updateFeatures'], $plan)<flux:button :href="route('central.plan-pages.edit', $plan)" icon="pencil-square">Modifier le plan</flux:button>@endcanany
            @can('createVersion', $plan)
                <form method="POST" action="{{ route('central.plan-pages.versions.store', $plan) }}">
                    @csrf
                    <input type="hidden" name="expected_state" value="{{ $state }}">
                    <input type="hidden" name="expected_latest_version" value="{{ $latestVersion }}">
                    <flux:button type="submit">Créer une nouvelle version</flux:button>
                </form>
            @endcan
            @can('activate', $plan)
                <form method="POST" action="{{ route('central.plan-pages.activate', $plan) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="expected_state" value="{{ $state }}">
                    <input type="hidden" name="is_active" value="{{ $plan->is_active ? '0' : '1' }}">
                    <flux:button type="submit">{{ $plan->is_active ? 'Désactiver la commercialisation' : 'Activer la commercialisation' }}</flux:button>
                </form>
            @endcan
            @can('archive', $plan)
                <form method="POST" action="{{ route('central.plan-pages.archive', $plan) }}" x-data x-on:submit="if (! window.confirm('Archiver cette version du plan ?')) $event.preventDefault()">
                    @csrf @method('DELETE')
                    <input type="hidden" name="expected_state" value="{{ $state }}">
                    <flux:button type="submit" variant="danger">Archiver</flux:button>
                </form>
            @endcan
            <flux:button :href="route('central.plan-pages.index')">Retour aux plans</flux:button>
        </div><p class="aydra-muted mt-4 text-sm">Activer une version désactive les autres versions commercialisées du même plan. Les abonnements existants ne changent pas.</p></section>
    </section>
</x-layouts::app>
