<x-layouts::app :title="$feature->name">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $feature->name }}</flux:heading>
        @if (session('status'))<flux:text role="status">{{ session('status') }}</flux:text>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <dl class="grid gap-3 sm:grid-cols-2">
            <div><dt>Code permanent</dt><dd>{{ $feature->code }}</dd></div>
            <div><dt>État</dt><dd>{{ $feature->trashed() ? 'Archivée' : ($feature->is_active ? 'Active' : 'Inactive') }}</dd></div>
            <div><dt>Type de valeur</dt><dd>{{ $feature->value_type->label() }}</dd></div>
            <div><dt>Unité</dt><dd>{{ $feature->unit ?? 'Non précisée' }}</dd></div>
            <div><dt>Portée</dt><dd>{{ $feature->quota_scope->label() }}</dd></div>
            <div><dt>Période</dt><dd>{{ $feature->period->label() }}</dd></div>
            <div><dt>Offres liées</dt><dd>{{ $references['plans'] }}</dd></div>
            <div><dt>Compteurs d’utilisation</dt><dd>{{ $references['usages'] }}</dd></div>
            <div><dt>Références dans les permissions</dt><dd>{{ $references['permissions'] }}</dd></div>
        </dl>
        @if ($feature->isSystem())
            <flux:text>Fonctionnalité système : sa définition et son activation sont protégées. Son nom reste modifiable.</flux:text>
        @elseif ($references['plans'] > 0)
            <flux:text>Cette fonctionnalité est liée à une offre. Sa désactivation et son archivage sont bloqués pour préserver les abonnements.</flux:text>
        @elseif (array_sum($references) > 0)
            <flux:text>Cette fonctionnalité possède des références : ses caractéristiques et son archivage restent protégés.</flux:text>
        @endif
        <div class="flex flex-wrap gap-3">
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
        </div>
    </section>
</x-layouts::app>
