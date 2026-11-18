<x-layouts::app :title="$tenant->shop_name">
    <section class="mx-auto flex w-full max-w-screen-xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="min-w-0 space-y-2"><h1 class="aydra-heading break-words text-3xl font-semibold tracking-tight sm:text-4xl">{{ $tenant->shop_name }}</h1><div class="flex flex-wrap items-center gap-2"><span class="aydra-muted text-sm">{{ $tenant->slug }}</span><flux:badge size="sm">{{ $tenant->status->label() }}</flux:badge></div></div><flux:button :href="route('central.tenants.index')" icon="arrow-left">Retour aux boutiques</flux:button></header>
        @php($primaryDomain = $tenant->domains->firstWhere('is_primary', true))
        @if ($tenant->status === \App\Enums\Central\Tenants\StatusEnum::ACTIVE && $tenant->provisioned_at !== null && filled($tenant->schema_version) && ! $deploymentPending && $primaryDomain?->isReadyAsPrimary())
            <flux:button class="max-w-full self-start whitespace-normal" :href="$primaryDomain->publicUrl()" target="_blank" rel="noopener noreferrer">Ouvrir la boutique — {{ $primaryDomain->domain }}</flux:button>
        @else
            <flux:text>État : {{ $deploymentPending || ($tenant->status === \App\Enums\Central\Tenants\StatusEnum::ACTIVE && ($tenant->provisioned_at === null || blank($tenant->schema_version))) ? 'En préparation' : $tenant->status->label() }}. Accès disponible lorsque la boutique sera prête et active.</flux:text>
        @endif
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" role="alert">{{ $errors->first() }}</flux:callout>@endif
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading text-lg font-semibold">Informations de la boutique</h2><p class="aydra-muted mt-1 text-sm">Propriétaire, domaine et état de préparation.</p><dl class="aydra-detail-grid mt-6 grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                'Nom public' => $tenant->shop_name, 'Libellé interne' => $tenant->internal_label, 'Slug' => $tenant->slug,
                'Propriétaire' => $tenant->owner?->name, 'E-mail du propriétaire' => $tenant->owner?->email,
                'État' => $tenant->status->label(), 'Domaines' => $tenant->domains->pluck('domain')->join(', '),
                'Base MySQL' => $tenant->getInternal('db_name'),
                ...(Gate::allows('viewAny', \App\Models\Central\Subscriptions\Subscription::class) ? [
                    'Abonnement' => $subscription?->plan?->name,
                    'Début de la période' => $subscription?->effectiveStartsAt()?->format('d/m/Y H:i'),
                    'Fin de la période' => $subscription?->effectiveEndsAt()?->format('d/m/Y H:i'),
                ] : []),
                'Priorité' => $tenant->activation_priority, 'Boutique principale' => $tenant->is_primary ? 'Oui' : 'Non',
                'Préparation terminée le' => $tenant->provisioned_at?->format('d/m/Y H:i'),
                'Version du schéma' => $tenant->schema_version, 'Créée le' => $tenant->created_at?->format('d/m/Y H:i'),
                'Hors quota depuis' => $tenant->over_quota_since_at?->format('d/m/Y H:i'),
                'Archivée le' => $tenant->deleted_at?->format('d/m/Y H:i'),
            ] as $label => $value)
                <div class="min-w-0"><dt class="font-medium">{{ $label }}</dt><dd class="break-words"><flux:text>{{ $value ?? 'Non renseigné' }}</flux:text></dd></div>
            @endforeach
        </dl></section>
        @if ($tenant->trashed() || $tenant->status === \App\Enums\Central\Tenants\StatusEnum::DELETED)
            <flux:callout>Cette boutique est archivée. Son activation n’est pas disponible.</flux:callout>
        @elseif ($tenant->status === \App\Enums\Central\Tenants\StatusEnum::ACTIVE)
            <flux:callout>Cette boutique est déjà active. Aucune activation supplémentaire n’est nécessaire.</flux:callout>
        @elseif ($tenant->status === \App\Enums\Central\Tenants\StatusEnum::PROVISIONING)
            <flux:callout>La préparation technique est en cours. La boutique deviendra automatiquement active après son initialisation si le propriétaire et son quota le permettent. Aucune validation de boutique supplémentaire n’est nécessaire.</flux:callout>
        @elseif ($tenant->status === \App\Enums\Central\Tenants\StatusEnum::PROVISIONING_FAILED)
            <flux:callout variant="warning">La préparation a échoué. Les informations sont conservées ; corrigez le problème technique puis demandez une reprise.</flux:callout>
            @can('retryProvisioning', $tenant)
                <form method="POST" action="{{ route('owner.shops.retry', $tenant) }}">@csrf <flux:button type="submit">Relancer la préparation</flux:button></form>
            @endcan
        @else
            @cannot('activate', $tenant)
                <flux:callout variant="warning">Votre compte ne possède pas la permission d’activer cette boutique. Contactez un administrateur habilité.</flux:callout>
            @else
                @if ($tenant->status === \App\Enums\Central\Tenants\StatusEnum::PROVISIONING_FAILED)
                    <flux:callout variant="warning">Activation indisponible : la préparation de la base de données a échoué. Un responsable technique doit corriger l’erreur et relancer la préparation.</flux:callout>
                @elseif ($tenant->schema_version === null || $tenant->provisioned_at === null)
                    <flux:callout variant="warning">La préparation technique de cette boutique doit être terminée avant de rétablir son accès.</flux:callout>
                @endif
            @endcannot
        @endif
        <flux:text>L’archivage conserve la base et ses données. Le nom de la base reste stable après un renommage. Les interfaces de gestion des boutiques restent en préparation.</flux:text>
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading mb-4 text-lg font-semibold">Actions de la boutique</h2>@include('central.pages.admin.tenants.actions', ['detail' => true])</section>
    </section>
</x-layouts::app>
