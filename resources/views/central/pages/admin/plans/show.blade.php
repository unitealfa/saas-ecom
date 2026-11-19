<x-layouts::app :title="$plan->name">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $plan->name }} — version {{ $plan->version }}</flux:heading>
        @if (session('status'))
            <flux:callout variant="success" icon="check-circle" role="status">
                {{ session('status') }}
            </flux:callout>
        @endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        @if ($plan->is_default)<flux:callout>Plan par défaut : attribution automatique gratuite aux nouveaux commerçants et à l’expiration des autres périodes.</flux:callout>@endif
        <dl class="grid gap-3 sm:grid-cols-2">
            <div><dt>Code permanent</dt><dd>{{ $plan->code }}</dd></div>
            <div><dt>Commercialisation</dt><dd>{{ $plan->trashed() ? 'Archivée' : ($plan->is_active ? 'Active' : 'Inactive') }}</dd></div>
            <div><dt>Prix mensuel</dt><dd>{{ $plan->monthly_price }} DZD</dd></div>
            <div><dt>Prix annuel</dt><dd>{{ $plan->annual_price }} DZD</dd></div>
            <div><dt>Description</dt><dd>{{ $plan->description ?: 'Non renseignée' }}</dd></div>
        </dl>
        @php($state = $plan->compositionState($plan->features))
        @can('update', $plan)
            <details class="rounded-xl border border-zinc-300 p-4 dark:border-zinc-700">
                <summary>Modifier les informations</summary>
                <flux:text>Une version liée à un abonnement ne peut plus être modifiée.</flux:text>
                <form method="POST" action="{{ route('central.plan-pages.update', $plan) }}" class="mt-4 flex max-w-2xl flex-col gap-4">
                    @csrf @method('PUT')
                    <input type="hidden" name="expected_state" value="{{ $state }}">
                    <flux:input name="name" label="Nom" :value="old('name', $plan->name)" required />
                    <flux:textarea name="description" label="Description">{{ old('description', $plan->description) }}</flux:textarea>
                    <flux:input type="number" min="0" step="0.01" name="monthly_price" label="Prix mensuel (DZD)" :value="old('monthly_price', $plan->monthly_price)" required />
                    <flux:input type="number" min="0" step="0.01" name="annual_price" label="Prix annuel (DZD)" :value="old('annual_price', $plan->annual_price)" required />
                    <div><flux:button type="submit" variant="primary">Enregistrer</flux:button></div>
                </form>
            </details>
        @endcan
        <div>
            <flux:heading level="2" size="lg">Fonctionnalités et quotas</flux:heading>
            <ul class="mt-3 space-y-2">
                @forelse ($plan->features as $item)
                    <li>{{ $item->feature->name }} ({{ $item->feature->code }}) : {{ $item->is_active ? 'incluse' : 'désactivée' }}@if ($item->limit !== null), limite {{ $item->limit }} {{ $item->feature->unit }}@endif</li>
                @empty
                    <li>Aucune fonctionnalité configurée.</li>
                @endforelse
            </ul>
        </div>
        {{-- L'offre gratuite reste protégée : seul ce formulaire versionne ses quotas équipe. --}}
        @if ($plan->is_active && $plan->version === $latestVersion)
            @can('configureTeamQuotas', $plan)
                @php($rolesLimit = $plan->features->first(fn ($item) => $item->feature->code === \App\Models\Central\Features\Feature::TEAM_CUSTOM_ROLES_CODE)?->limit)
                @php($membersLimit = $plan->features->first(fn ($item) => $item->feature->code === \App\Models\Central\Features\Feature::TEAM_MEMBERS_CODE)?->limit)
                <details class="rounded-xl border border-zinc-300 p-4 dark:border-zinc-700">
                    <summary>Configurer les quotas d'équipe de l'offre gratuite</summary>
                    <flux:text>Cette action prépare une nouvelle version gratuite inactive. L'activation est séparée ; les abonnements existants et le quota de boutiques sont conservés. Aucun nombre n'est prérempli si le quota n'existe pas.</flux:text>
                    <form method="POST" action="{{ route('central.plan-pages.free-team-quotas.update', $plan) }}" class="mt-4 flex max-w-xl flex-col gap-4">
                        @csrf
                        <input type="hidden" name="expected_state" value="{{ $state }}">
                        <input type="hidden" name="expected_latest_version" value="{{ $latestVersion }}">
                        <flux:input type="number" name="max_roles" min="0" max="2147483647" step="1" required label="Nombre maximal de rôles personnalisés par boutique" :value="old('max_roles', $rolesLimit)" />
                        <flux:input type="number" name="max_members" min="0" max="2147483647" step="1" required label="Nombre maximal de membres par boutique" :value="old('max_members', $membersLimit)" />
                        <flux:button type="submit" variant="primary">Préparer la version gratuite avec ces quotas</flux:button>
                    </form>
                </details>
            @endcan
        @endif
        @if (! $plan->is_active && $plan->version === $latestVersion)
            @can('activateTeamQuotaVersion', $plan)
                <form method="POST" action="{{ route('central.plan-pages.free-team-quotas.activate', $plan) }}" class="rounded-xl border border-zinc-300 p-4 dark:border-zinc-700">
                    @csrf @method('PATCH')
                    <input type="hidden" name="expected_state" value="{{ $state }}">
                    <flux:text>Cette activation distincte commercialise la dernière version gratuite des quotas équipe.</flux:text>
                    <flux:button type="submit" variant="primary">Activer cette version gratuite</flux:button>
                </form>
            @endcan
        @endif
        @can('updateFeatures', $plan)
            <details class="rounded-xl border border-zinc-300 p-4 dark:border-zinc-700">
                <summary>Définir les fonctionnalités et quotas</summary>
                <flux:text>Sélectionnez les fonctionnalités à associer. Une limite vide signifie sans limite ; une version liée à un abonnement ne peut plus être modifiée.</flux:text>
                <form method="POST" action="{{ route('central.plan-pages.features.update', $plan) }}" class="mt-4 flex flex-col gap-4">
                    @csrf @method('PUT')
                    <input type="hidden" name="_composition" value="1">
                    <input type="hidden" name="expected_state" value="{{ $state }}">
                    @php($currentFeatures = $plan->features->keyBy('feature_id'))
                    @php($submittedComposition = old('_composition') === '1')
                    @php($selectedFeatures = is_array(old('selected')) ? old('selected') : [])
                    @php($enabledFeatures = is_array(old('enabled')) ? old('enabled') : [])
                    @foreach ($availableFeatures->merge($plan->features->pluck('feature'))->unique('id')->sortBy('name') as $feature)
                        @php($current = $currentFeatures->get($feature->id))
                        <div class="flex flex-wrap items-center gap-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <label><input type="checkbox" name="selected[]" value="{{ $feature->uuid }}" @checked($submittedComposition ? in_array($feature->uuid, $selectedFeatures, true) : $current !== null)> {{ $feature->name }} ({{ $feature->code }})</label>
                            <label><input type="checkbox" name="enabled[{{ $feature->uuid }}]" value="1" @checked($submittedComposition ? isset($enabledFeatures[$feature->uuid]) : ($current?->is_active ?? true))> Activée dans ce plan</label>
                            @if ($feature->value_type === \App\Enums\Central\Features\ValueTypeEnum::QUANTITY)
                                @php($limit = old('limits.'.$feature->uuid, $current?->limit))
                                <label>Limite <input class="rounded border border-zinc-300 bg-transparent p-1" type="number" min="0" name="limits[{{ $feature->uuid }}]" value="{{ is_scalar($limit) ? $limit : '' }}"> {{ $feature->unit }}</label>
                            @endif
                            @if ($feature->trashed() || ! $feature->is_active)<span>Indisponible : conserver sans changement ou retirer.</span>@endif
                        </div>
                    @endforeach
                    <div><flux:button type="submit" variant="primary">Enregistrer la composition</flux:button></div>
                </form>
            </details>
        @endcan
        <div class="flex flex-wrap gap-3">
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
        </div>
        <flux:text>Activer une version désactive les autres versions commercialisées du même plan. Les abonnements existants ne changent pas.</flux:text>
    </section>
</x-layouts::app>
