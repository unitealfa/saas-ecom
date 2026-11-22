<x-layouts::app :title="$feature->name">
    <section class="mx-auto flex w-full max-w-screen-xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="min-w-0 space-y-2"><h1 class="aydra-heading break-words text-3xl font-semibold tracking-tight sm:text-4xl">{{ $feature->name }}</h1><div class="flex flex-wrap items-center gap-2"><span class="aydra-muted text-sm">{{ $feature->code }}</span><flux:badge size="sm" :color="$feature->trashed() ? 'zinc' : ($feature->is_active ? 'green' : 'amber')">{{ $feature->trashed() ? 'Archivée' : ($feature->is_active ? 'Active' : 'Inactive') }}</flux:badge>@if ($feature->isSystem())<flux:badge size="sm">Système</flux:badge>@endif</div></div><flux:button :href="route('central.features.index')" icon="arrow-left">Retour au catalogue</flux:button></header>
        @if (session('status'))<flux:callout role="status">{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading text-lg font-semibold">Informations de la fonctionnalité</h2><p class="aydra-muted mt-1 text-sm">Configuration, état et références.</p><dl class="aydra-detail-grid mt-6 grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
            <div><dt>Code permanent</dt><dd>{{ $feature->code }}</dd></div>
            <div><dt>État</dt><dd>{{ $feature->trashed() ? 'Archivée' : ($feature->is_active ? 'Active' : 'Inactive') }}</dd></div>
            <div><dt>Type de valeur</dt><dd>{{ $feature->value_type->label() }}</dd></div>
            <div><dt>Unité</dt><dd>{{ $feature->unit ?? 'Non précisée' }}</dd></div>
            <div><dt>Portée</dt><dd>{{ $feature->quota_scope->label() }}</dd></div>
            <div><dt>Période</dt><dd>{{ $feature->period->label() }}</dd></div>
            <div><dt>Offres liées</dt><dd>{{ $references['plans'] }}</dd></div>
            <div><dt>Compteurs d’utilisation</dt><dd>{{ $references['usages'] }}</dd></div>
            <div><dt>Références dans les permissions</dt><dd>{{ $references['permissions'] }}</dd></div>
        </dl></section>
        @if ($feature->isSystem())
            <flux:callout>Fonctionnalité système : sa définition et son activation sont protégées. Son nom reste modifiable.</flux:callout>
        @elseif ($references['plans'] > 0)
            <flux:callout>Cette fonctionnalité est liée à une offre. Sa désactivation et son archivage sont bloqués pour préserver les abonnements.</flux:callout>
        @elseif (array_sum($references) > 0)
            <flux:callout>Cette fonctionnalité possède des références : ses caractéristiques et son archivage restent protégés.</flux:callout>
        @endif
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading mb-4 text-lg font-semibold">Actions</h2><div class="flex flex-wrap gap-3">
            @can('update', $feature)<flux:button :href="route('central.features.edit', $feature)">Modifier</flux:button>@endcan
            @can('activate', $feature)
                @if (! $feature->is_active || $references['plans'] === 0)
                    <form method="POST" action="{{ route('central.features.activate', $feature) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="expected_state" value="{{ $feature->managementState() }}">
                        <input type="hidden" name="is_active" value="{{ $feature->is_active ? '0' : '1' }}">
                        <flux:button type="submit">{{ $feature->is_active ? 'Désactiver' : 'Activer' }}</flux:button>
                    </form>
                @endif
            @endcan
            @can('archive', $feature)
                @if (array_sum($references) === 0)
                    <form method="POST" action="{{ route('central.features.archive', $feature) }}" x-data x-on:submit="if (! window.confirm('Archiver cette fonctionnalité inutilisée ? Son code restera réservé.')) $event.preventDefault()">
                        @csrf @method('DELETE')
                        <input type="hidden" name="expected_state" value="{{ $feature->managementState() }}">
                        <flux:button type="submit" variant="danger">Archiver</flux:button>
                    </form>
                @endif
            @endcan
            <flux:button :href="route('central.features.index')">Retour au catalogue</flux:button>
        </div></section>
    </section>
</x-layouts::app>
