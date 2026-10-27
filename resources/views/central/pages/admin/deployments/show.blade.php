<x-layouts::app :title="__('Déploiement')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Déploiement — {{ $deployment->tenant?->shop_name }}</flux:heading>
        @if (session('status'))<flux:text role="status">{{ session('status') }}</flux:text>@endif
        @foreach ($errors->all() as $message)<flux:text role="alert">{{ $message }}</flux:text>@endforeach
        <dl class="grid gap-3 sm:grid-cols-2">
            <div><dt>Opération</dt><dd>{{ $deployment->operation->label() }}</dd></div>
            <div><dt>État</dt><dd>{{ $deployment->status->label() }}</dd></div>
            <div><dt>Version de départ</dt><dd>{{ $deployment->source_version ?? 'Première installation' }}</dd></div>
            <div><dt>Version cible</dt><dd>{{ $deployment->target_version }}</dd></div>
            <div><dt>Tentative</dt><dd>{{ $deployment->attempt_number }}</dd></div>
            <div><dt>Début</dt><dd>{{ $deployment->started_at?->format('d/m/Y H:i:s') ?? 'Pas encore démarré' }}</dd></div>
            <div><dt>Fin</dt><dd>{{ $deployment->ended_at?->format('d/m/Y H:i:s') ?? 'Non terminé' }}</dd></div>
            <div><dt>Version actuelle de la boutique</dt><dd>{{ $deployment->tenant?->schema_version ?? 'Non préparée' }}</dd></div>
        </dl>
        @if ($error !== null)
            <div role="alert"><flux:heading>Diagnostic nettoyé</flux:heading><flux:text>{{ $error }}</flux:text></div>
        @endif
        @if ($isLatest)
            <div class="flex flex-wrap gap-3">
                @can('provision', $deployment)
                    <form method="POST" action="{{ route('central.deployments.provision', $deployment) }}">
                        @csrf
                        <input type="hidden" name="expected_state" value="{{ $deployment->managementState() }}">
                        <flux:button type="submit" variant="primary">Lancer le provisionnement</flux:button>
                    </form>
                @endcan
                @can('retry', $deployment)
                    <form method="POST" action="{{ route('central.deployments.retry', $deployment) }}">
                        @csrf
                        <input type="hidden" name="expected_state" value="{{ $deployment->managementState() }}">
                        <flux:button type="submit" variant="primary">Relancer une tentative</flux:button>
                    </form>
                @endcan
            </div>
            @if ($deployment->tenant !== null && filled($deployment->tenant->schema_version) && strcmp($deployment->tenant->schema_version, $latestVersion) < 0)
                @can('migrate', [\App\Models\Central\Deployments\TenantSchemaDeployment::class, $deployment->tenant])
                    <form method="POST" action="{{ route('central.deployments.migrate', $deployment) }}" class="flex flex-col items-start gap-3" x-data x-on:submit="if (! window.confirm('Lancer cette migration ? La boutique sera temporairement indisponible.')) $event.preventDefault()">
                        @csrf
                        <input type="hidden" name="expected_state" value="{{ $deployment->managementState() }}">
                        <flux:select name="target_version" label="Version prévue dans le code">
                            <option value="{{ $latestVersion }}">{{ $latestVersion }}</option>
                        </flux:select>
                        <flux:button type="submit" variant="primary">Lancer la migration</flux:button>
                    </form>
                @endcan
            @endif
        @else
            <flux:text>Une opération plus récente existe. Consultez sa fiche avant toute nouvelle action.</flux:text>
        @endif
        <flux:heading>Historique des tentatives</flux:heading>
        <ul class="flex flex-col gap-2">
            @foreach ($attempts as $attempt)
                <li><a class="underline" href="{{ route('central.deployments.show', $attempt) }}">Tentative {{ $attempt->attempt_number }} — {{ $attempt->status->label() }}</a></li>
            @endforeach
        </ul>
        <flux:text>Les reprises conservent les données et l’historique. Aucun retour automatique à une ancienne version de la base.</flux:text>
        <flux:button :href="route('central.deployments.show', $deployment)">Actualiser l’état</flux:button>
        <flux:button :href="route('central.deployments.index')">Retour aux déploiements</flux:button>
    </section>
</x-layouts::app>
