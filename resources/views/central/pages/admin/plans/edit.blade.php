<x-layouts::app :title="__('Modifier le plan')">
    <section class="mx-auto flex w-full max-w-screen-xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Modifier le plan</h1><p class="aydra-muted text-sm sm:text-base">{{ $plan->name }} — version {{ $plan->version }}</p></div><flux:button :href="route('central.plan-pages.show', $plan)" icon="arrow-left">Retour au plan</flux:button></header>
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        @php($state = $plan->compositionState($plan->features))
        @can('update', $plan)
            <section class="aydra-users-panel p-5 sm:p-6">
                <h2 class="aydra-heading text-lg font-semibold">Modifier les informations</h2>
                <flux:text class="mt-2">Une version liée à un abonnement ne peut plus être modifiée.</flux:text>
                <form method="POST" action="{{ route('central.plan-pages.update', $plan) }}" class="mt-4 flex max-w-2xl flex-col gap-4">
                    @csrf @method('PUT')
                    <input type="hidden" name="expected_state" value="{{ $state }}">
                    <flux:input name="name" label="Nom" :value="old('name', $plan->name)" required />
                    <flux:textarea name="description" label="Description">{{ old('description', $plan->description) }}</flux:textarea>
                    <flux:input type="number" min="0" step="0.01" name="monthly_price" label="Prix mensuel (DZD)" :value="old('monthly_price', $plan->monthly_price)" required />
                    <flux:input type="number" min="0" step="0.01" name="annual_price" label="Prix annuel (DZD)" :value="old('annual_price', $plan->annual_price)" required />
                    <div><flux:button type="submit" variant="primary">Enregistrer</flux:button></div>
                </form>
            </section>
        @endcan
        @can('updateFeatures', $plan)
            <section class="aydra-users-panel p-5 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="aydra-heading text-lg font-semibold">Fonctionnalités et quotas</h2><p class="aydra-muted mt-1 max-w-2xl text-sm">Sélectionnez les fonctionnalités à associer et définissez leurs limites. Une limite vide signifie sans limite ; une version liée à un abonnement ne peut plus être modifiée.</p></div></div>
                <form method="POST" action="{{ route('central.plan-pages.features.update', $plan) }}" class="mt-5 flex flex-col gap-4" x-data="{ search: '' }">
                    @csrf @method('PUT')
                    <input type="hidden" name="_composition" value="1">
                    <input type="hidden" name="expected_state" value="{{ $state }}">
                    @php($currentFeatures = $plan->features->keyBy('feature_id'))
                    @php($submittedComposition = old('_composition') === '1')
                    @php($selectedFeatures = is_array(old('selected')) ? old('selected') : [])
                    @php($enabledFeatures = is_array(old('enabled')) ? old('enabled') : [])
                    <flux:input type="search" label="Rechercher une fonctionnalité" icon="magnifying-glass" placeholder="Nom ou code" x-model="search" class="max-w-md" />
                    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                    @forelse ($availableFeatures->merge($plan->features->pluck('feature'))->unique('id')->sortBy('name') as $feature)
                        @php($current = $currentFeatures->get($feature->id))
                        <div class="grid gap-4 border-b border-zinc-200 p-4 last:border-b-0 dark:border-zinc-700 lg:grid-cols-[minmax(0,1fr)_auto_auto_minmax(9rem,12rem)] lg:items-center" data-search="{{ mb_strtolower($feature->name.' '.$feature->code) }}" x-show="$el.dataset.search.includes(search.trim().toLocaleLowerCase())">
                            <div class="min-w-0"><p class="aydra-heading break-words text-sm font-semibold">{{ $feature->name }}</p><p class="aydra-muted mt-1 break-all text-xs">{{ $feature->code }}</p>@if ($feature->trashed() || ! $feature->is_active)<p class="mt-2 text-xs text-amber-700 dark:text-amber-300">Indisponible : conserver sans changement ou retirer.</p>@endif</div>
                            <label class="flex items-center gap-2 text-sm"><input class="size-4 accent-blue-600" type="checkbox" name="selected[]" value="{{ $feature->uuid }}" @checked($submittedComposition ? in_array($feature->uuid, $selectedFeatures, true) : $current !== null)> Associer</label>
                            <label class="flex items-center gap-2 text-sm"><input class="size-4 accent-blue-600" type="checkbox" name="enabled[{{ $feature->uuid }}]" value="1" @checked($submittedComposition ? isset($enabledFeatures[$feature->uuid]) : ($current?->is_active ?? true))> Activée</label>
                            @if ($feature->value_type === \App\Enums\Central\Features\ValueTypeEnum::QUANTITY)
                                @php($limit = old('limits.'.$feature->uuid, $current?->limit))
                                <label class="aydra-muted block text-sm">Limite {{ $feature->unit }}<input class="mt-1 block w-full rounded-lg border border-zinc-300 bg-transparent px-3 py-2 text-sm text-zinc-900 dark:border-zinc-600 dark:text-zinc-100" type="number" min="0" name="limits[{{ $feature->uuid }}]" value="{{ is_scalar($limit) ? $limit : '' }}"></label>
                            @endif
                        </div>
                    @empty <p class="aydra-muted p-4 text-sm">Aucune fonctionnalité disponible.</p> @endforelse
                    </div>
                    <div><flux:button type="submit" variant="primary">Enregistrer la composition</flux:button></div>
                </form>
            </section>
        @endcan
    </section>
</x-layouts::app>
