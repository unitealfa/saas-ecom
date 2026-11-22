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
        <div class="flex flex-wrap gap-3">
            @can('createVersion', $plan)
                @php($restoreDraft = old('draft.preview') === '1' && ! $errors->hasAny(['expected_state', 'expected_latest_version']))
                @php($confirmCopy = $restoreDraft && $errors->has('copy_confirmation'))
                @php($draftValue = static fn (string $key, mixed $fallback) => $restoreDraft && is_scalar($value = old('draft.'.$key, $fallback)) ? $value : ($restoreDraft ? '' : $fallback))
                @php($versionFeatures = $plan->features->keyBy('feature_id'))
                @php($draftSelected = (array) old('draft.selected', []))
                <flux:modal.trigger name="create-plan-version">
                    <flux:button type="button">Créer une nouvelle version</flux:button>
                </flux:modal.trigger>
                <flux:modal name="create-plan-version" class="w-full md:max-w-3xl" :dismissible="false">
                    <form method="POST" action="{{ route('central.plan-pages.versions.store', $plan) }}" class="flex flex-col gap-4"
                        x-data="{ confirmed: @js($confirmCopy) }" x-ref="versionForm"
                        @if ($restoreDraft) x-init="$nextTick(() => $flux.modal('create-plan-version').show())" @endif>
                        @csrf
                        <input type="hidden" name="expected_state" value="{{ $state }}">
                        <input type="hidden" name="expected_latest_version" value="{{ $latestVersion }}">
                        <input type="hidden" name="draft[preview]" value="1">
                        <flux:heading size="lg">Créer la version {{ $latestVersion + 1 }}</flux:heading>
                        @if ($confirmCopy)
                            <flux:callout variant="warning" role="alert" x-show="confirmed">{{ $errors->first('copy_confirmation') }}</flux:callout>
                            <input type="hidden" name="draft[confirmed_copy]" value="1" x-bind:disabled="! confirmed">
                        @elseif ($restoreDraft && $errors->any())
                            <flux:callout variant="danger" role="alert">
                                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                            </flux:callout>
                        @endif
                        <div x-show="! confirmed" class="max-h-[60vh] space-y-4 overflow-y-auto p-1">
                            <details open class="rounded-lg border border-zinc-300 p-4 dark:border-zinc-700">
                                <summary>Modifier les informations</summary>
                                <div class="mt-3 flex flex-col gap-3">
                                    @can('update', $plan)
                                        <flux:input name="draft[information][name]" label="Nom" :value="$draftValue('information.name', $suggestedVersionName)" maxlength="255" required />
                                        <flux:textarea name="draft[information][description]" label="Description">{{ $draftValue('information.description', $plan->description) }}</flux:textarea>
                                        <flux:input type="number" min="0" step="0.01" name="draft[information][monthly_price]" label="Prix mensuel (DZD)" :value="$draftValue('information.monthly_price', $plan->monthly_price)" required />
                                        <flux:input type="number" min="0" step="0.01" name="draft[information][annual_price]" label="Prix annuel (DZD)" :value="$draftValue('information.annual_price', $plan->annual_price)" required />
                                    @else
                                        <flux:text>{{ $suggestedVersionName }}</flux:text>
                                        <flux:text>{{ $plan->description }}</flux:text>
                                        <flux:text>{{ $plan->monthly_price }} DZD par mois · {{ $plan->annual_price }} DZD par an</flux:text>
                                    @endcan
                                </div>
                            </details>
                            <details open class="rounded-lg border border-zinc-300 p-4 dark:border-zinc-700">
                                <summary>Définir les fonctionnalités et quotas</summary>
                                <div class="mt-3 flex flex-col gap-3">
                                    @can('updateVersionFeatures', $plan)
                                        <input type="hidden" name="draft[edit_features]" value="1">
                                        @foreach ($availableFeatures->merge($plan->features->pluck('feature'))->unique('id')->sortBy('name') as $feature)
                                            @php($current = $versionFeatures->get($feature->id))
                                            <fieldset class="flex flex-wrap items-center gap-3">
                                                <legend class="mb-1 font-medium">{{ $feature->name }}</legend>
                                                <label><input type="checkbox" name="draft[selected][]" value="{{ $feature->uuid }}" @checked($restoreDraft ? in_array($feature->uuid, $draftSelected, true) : $current !== null)> Inclure</label>
                                                <label><input type="checkbox" name="draft[enabled][{{ $feature->uuid }}]" value="1" @checked($restoreDraft ? old('draft.enabled.'.$feature->uuid) === '1' : ($current?->is_active ?? true))> Activer</label>
                                                @if ($feature->value_type === \App\Enums\Central\Features\ValueTypeEnum::QUANTITY)
                                                    <label>Limite
                                                        <input class="w-28 rounded border border-zinc-300 bg-transparent p-1" type="number" min="0" name="draft[limits][{{ $feature->uuid }}]" value="{{ $restoreDraft ? $draftValue('limits.'.$feature->uuid, '') : $current?->limit }}">
                                                        {{ $feature->unit }}
                                                    </label>
                                                @endif
                                                @if ($feature->trashed() || ! $feature->is_active)<flux:text>Indisponible</flux:text>@endif
                                            </fieldset>
                                        @endforeach
                                    @else
                                        @forelse ($plan->features as $item)
                                            <flux:text>{{ $item->feature->name }} · {{ $item->is_active ? 'Activée' : 'Désactivée' }}@if ($item->limit !== null) · {{ $item->limit }} {{ $item->feature->unit }}@endif</flux:text>
                                        @empty
                                            <flux:text>Aucune fonctionnalité configurée.</flux:text>
                                        @endforelse
                                    @endcan
                                </div>
                            </details>
                        </div>
                        <div class="flex justify-end gap-2">
                            <flux:modal.close><flux:button type="button" x-on:click="confirmed = false; $refs.versionForm.reset()"><span x-text="confirmed ? 'Non' : 'Annuler'">Annuler</span></flux:button></flux:modal.close>
                            <flux:button type="submit" variant="primary"><span x-text="confirmed ? 'Oui' : 'Créer'">Créer</span></flux:button>
                        </div>
                    </form>
                </flux:modal>
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
        <flux:text>Activer une version désactive les autres versions commercialisées du même plan, sauf le plan par défaut. Les abonnements existants ne changent pas.</flux:text>
    </section>
</x-layouts::app>
