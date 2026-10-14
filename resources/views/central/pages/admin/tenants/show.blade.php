<x-layouts::app :title="$tenant->shop_name">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $tenant->shop_name }}</flux:heading>
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
        <flux:text>L’archivage conserve la base et ses données. Le nom de la base reste stable après un renommage. Les interfaces de gestion des boutiques restent en préparation.</flux:text>
        @include('central.pages.admin.tenants.actions', ['detail' => true])
        @can('updatePriorities', $tenant)
            <form method="POST" action="{{ route('central.tenants.priorities', $tenant) }}" class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                @csrf @method('PUT')
                <flux:heading level="2">Priorités des boutiques de ce propriétaire</flux:heading>
                <flux:text>Classez toutes ses boutiques de 1 à {{ $ownerShops->count() }}, sans doublon. Les boutiques éligibles sont choisies sous le quota de l’offre ; les suspensions et les préparations ne sont pas annulées.</flux:text>
                @foreach ($ownerShops as $shop)
                    <flux:input type="number" name="priorities[{{ $shop->uuid }}]" :label="$shop->shop_name.' — '.$shop->status->label()" min="1" :max="$ownerShops->count()"
                        :value="old('priorities.'.$shop->uuid, $shop->activation_priority ?? ($loop->index + 1))" required />
                @endforeach
                <flux:input name="reason" label="Motif facultatif" :value="old('reason')" />
                <flux:button type="submit" variant="primary">Enregistrer les priorités</flux:button>
            </form>
        @endcan
        <flux:button :href="route('central.tenants.index')">Retour aux boutiques</flux:button>
    </section>
</x-layouts::app>
