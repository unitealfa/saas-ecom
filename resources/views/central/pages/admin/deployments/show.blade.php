<x-layouts::app :title="__('Déploiement')">
    <section class="mx-auto flex w-full max-w-screen-xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="min-w-0 space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Déploiement</h1><div class="flex flex-wrap items-center gap-2"><span class="aydra-muted text-sm">{{ $deployment->tenant?->shop_name ?? 'Boutique indisponible' }}</span><flux:badge size="sm" :color="match ($deployment->status) { \App\Enums\Central\Deployments\StatusEnum::SUCCEEDED => 'green', \App\Enums\Central\Deployments\StatusEnum::FAILED => 'red', \App\Enums\Central\Deployments\StatusEnum::PENDING, \App\Enums\Central\Deployments\StatusEnum::RUNNING => 'amber', default => 'zinc' }">{{ $deployment->status->label() }}</flux:badge></div></div><div class="flex flex-wrap gap-2"><flux:button :href="route('central.deployments.show', $deployment)" icon="arrow-path">Actualiser l’état</flux:button><flux:button :href="route('central.deployments.index')" icon="arrow-left">Retour aux déploiements</flux:button></div></header>
        @if (session('status'))<flux:callout role="status">{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach</flux:callout>@endif
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading text-lg font-semibold">Informations du déploiement</h2><p class="aydra-muted mt-1 text-sm">Opération, versions et durée de cette tentative.</p><dl class="aydra-detail-grid mt-6 grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
            <div><dt>Opération</dt><dd>{{ $deployment->operation->label() }}</dd></div>
            <div><dt>État</dt><dd>{{ $deployment->status->label() }}</dd></div>
            <div><dt>Version de départ</dt><dd>{{ $deployment->source_version ?? 'Première installation' }}</dd></div>
            <div><dt>Version cible</dt><dd>{{ $deployment->target_version }}</dd></div>
            <div><dt>Tentative</dt><dd>{{ $deployment->attempt_number }}</dd></div>
            <div><dt>Début</dt><dd>{{ $deployment->started_at?->format('d/m/Y H:i:s') ?? 'Pas encore démarré' }}</dd></div>
            <div><dt>Fin</dt><dd>{{ $deployment->ended_at?->format('d/m/Y H:i:s') ?? 'Non terminé' }}</dd></div>
            <div><dt>Durée d’exécution</dt><dd>{{ $deployment->durationLabel() }}</dd></div>
            <div><dt>Version actuelle de la boutique</dt><dd>{{ $deployment->tenant?->schema_version ?? 'Non préparée' }}</dd></div>
        </dl></section>
        @if ($error !== null)
            <flux:callout variant="warning" heading="Diagnostic" role="alert">{{ $error }}</flux:callout>
        @endif
        @if ($isLatest)
            <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading text-lg font-semibold">Actions disponibles</h2><p class="aydra-muted mt-1 text-sm">Les opérations sont exécutées par le worker central.</p><div class="mt-4 flex flex-wrap gap-3">
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
                    <form method="POST" action="{{ route('central.deployments.migrate', $deployment) }}" class="mt-4 flex flex-wrap items-end gap-3" x-data x-on:submit="if (! window.confirm('Lancer cette migration ? La boutique sera temporairement indisponible.')) $event.preventDefault()">
                        @csrf
                        <input type="hidden" name="expected_state" value="{{ $deployment->managementState() }}">
                        <flux:select name="target_version" label="Version prévue dans le code">
                            <option value="{{ $latestVersion }}">{{ $latestVersion }}</option>
                        </flux:select>
                        <flux:button type="submit" variant="primary">Lancer la migration</flux:button>
                    </form>
                @endcan
            @endif
            </section>
        @else
            <flux:callout>Une opération plus récente existe. Consultez sa fiche avant toute nouvelle action.</flux:callout>
        @endif
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading text-lg font-semibold">Historique des tentatives</h2><p class="aydra-muted mt-1 text-sm">Les reprises conservent les données et l’historique. Aucun retour automatique à une ancienne version de la base.</p>
        <ul class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach ($attempts as $attempt)
                <li class="flex flex-wrap items-center justify-between gap-3 py-3"><div><span class="aydra-heading text-sm font-medium">Tentative {{ $attempt->attempt_number }}</span><span class="aydra-muted ml-2 text-sm">{{ $attempt->status->label() }}</span></div><flux:button size="sm" :href="route('central.deployments.show', $attempt)">Consulter</flux:button></li>
            @endforeach
        </ul>
        </section>
    </section>
</x-layouts::app>
