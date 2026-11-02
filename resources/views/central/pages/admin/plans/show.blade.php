<x-layouts::app :title="$plan->name">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $plan->name }} — version {{ $plan->version }}</flux:heading>
        @if (session('status'))<flux:text role="status">{{ session('status') }}</flux:text>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
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
        @can('updateFeatures', $plan)
            <details class="rounded-xl border border-zinc-300 p-4 dark:border-zinc-700">
                <summary>Définir les fonctionnalités et quotas</summary>
                <flux:text>Sélectionnez les fonctionnalités à associer. Une limite vide signifie sans limite ; une version liée à un abonnement ne peut plus être modifiée.</flux:text>
                <form method="POST" action="{{ route('central.plan-pages.features.update', $plan) }}" class="mt-4 flex flex-col gap-4">
                    @csrf @method('PUT')
                    <input type="hidden" name="expected_state" value="{{ $state }}">
                    @php($currentFeatures = $plan->features->keyBy('feature_id'))
                    @foreach ($availableFeatures->merge($plan->features->pluck('feature'))->unique('id')->sortBy('name') as $feature)
                        @php($current = $currentFeatures->get($feature->id))
                        <div class="flex flex-wrap items-center gap-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <label><input type="checkbox" name="selected[]" value="{{ $feature->uuid }}" @checked($current !== null)> {{ $feature->name }} ({{ $feature->code }})</label>
                            <label><input type="checkbox" name="enabled[{{ $feature->uuid }}]" value="1" @checked($current?->is_active ?? true)> Activée dans ce plan</label>
                            @if ($feature->value_type === \App\Enums\Central\Features\ValueTypeEnum::QUANTITY)
                                <label>Limite <input class="rounded border border-zinc-300 bg-transparent p-1" type="number" min="0" name="limits[{{ $feature->uuid }}]" value="{{ $current?->limit }}"> {{ $feature->unit }}</label>
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
