<div class="flex flex-wrap gap-3">
    @if (! ($detail ?? false))<flux:button :href="route('central.tenants.show', $tenant)">Informations</flux:button>@endif
    @can('update', $tenant)<flux:button :href="route('central.tenants.edit', $tenant)">Modifier</flux:button>@endcan
    @can('viewHistory', $tenant)<flux:button :href="route('central.tenants.history', $tenant)">Historique</flux:button>@endcan
    @if ($detail ?? false)
        @foreach (['suspend' => 'Suspendre', 'activate' => 'Réactiver', 'archive' => 'Archiver'] as $action => $label)
            @can($action, $tenant)
                @if ($action === 'archive' || ($tenant->schema_version !== null && $tenant->provisioned_at !== null
                    && ($action === 'suspend' ? in_array($tenant->status, [\App\Enums\Central\Tenants\StatusEnum::PROVISIONING,
                            \App\Enums\Central\Tenants\StatusEnum::ACTIVE, \App\Enums\Central\Tenants\StatusEnum::INACTIVE,
                            \App\Enums\Central\Tenants\StatusEnum::OVER_QUOTA], true)
                        : in_array($tenant->status, [\App\Enums\Central\Tenants\StatusEnum::PROVISIONING, \App\Enums\Central\Tenants\StatusEnum::INACTIVE,
                            \App\Enums\Central\Tenants\StatusEnum::SUSPENDED, \App\Enums\Central\Tenants\StatusEnum::OVER_QUOTA], true))))
                    <form method="POST" action="{{ route('central.tenants.'.$action, $tenant) }}" class="flex flex-col gap-2"
                        x-data x-on:submit="if (!confirm('Confirmer cette action sur la boutique ?')) $event.preventDefault()">
                        @csrf
                        <input type="hidden" name="expected_status" value="{{ $tenant->status->value }}">
                        <flux:input name="reason" label="Motif facultatif" />
                        <flux:button type="submit" :variant="$action === 'archive' ? 'danger' : 'outline'">{{ $action === 'activate' && $tenant->status === \App\Enums\Central\Tenants\StatusEnum::PROVISIONING ? 'Activer' : $label }}</flux:button>
                    </form>
                @endif
            @endcan
        @endforeach
    @endif
</div>
