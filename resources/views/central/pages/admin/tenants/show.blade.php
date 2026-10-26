<x-layouts::app :title="$tenant->shop_name">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $tenant->shop_name }}</flux:heading>
        @php($primaryDomain = $tenant->domains->firstWhere('is_primary', true))
        @if ($primaryDomain?->isReadyAsPrimary())
            <flux:button :href="$primaryDomain->publicUrl()" target="_blank" rel="noopener noreferrer">Ouvrir la boutique — {{ $primaryDomain->domain }}</flux:button>
        @endif
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" role="alert">{{ $errors->first() }}</flux:callout>@endif
        <dl class="grid gap-3 rounded-xl border border-zinc-200 p-4 sm:grid-cols-2 dark:border-zinc-700">
            @foreach ([
                'Nom public' => $tenant->shop_name, 'Libellé interne' => $tenant->internal_label, 'Slug' => $tenant->slug,
                'Propriétaire' => $tenant->owner?->name, 'E-mail du propriétaire' => $tenant->owner?->email,
                'État' => $tenant->status->label(), 'Domaines' => $tenant->domains->pluck('domain')->join(', '),
                'Base MySQL' => $tenant->getInternal('db_name'), 'Abonnement' => $subscription?->plan?->name,
                'Début de la période' => $subscription?->period_starts_at?->format('d/m/Y H:i'),
                'Fin de la période' => $subscription?->period_ends_at?->format('d/m/Y H:i'),
                'Priorité' => $tenant->activation_priority, 'Boutique principale' => $tenant->is_primary ? 'Oui' : 'Non',
                'Préparation terminée le' => $tenant->provisioned_at?->format('d/m/Y H:i'),
                'Version du schéma' => $tenant->schema_version, 'Créée le' => $tenant->created_at?->format('d/m/Y H:i'),
                'Hors quota depuis' => $tenant->over_quota_since_at?->format('d/m/Y H:i'),
                'Archivée le' => $tenant->deleted_at?->format('d/m/Y H:i'),
            ] as $label => $value)
                <dt class="font-medium">{{ $label }}</dt><dd>{{ $value ?? 'Non renseigné' }}</dd>
            @endforeach
        </dl>
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
        @include('central.pages.admin.tenants.actions', ['detail' => true])
        <flux:button :href="route('central.tenants.index')">Retour aux boutiques</flux:button>
    </section>
</x-layouts::app>
