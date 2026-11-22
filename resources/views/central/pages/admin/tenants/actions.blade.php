@unless ($inActionMenu ?? false)<div class="flex flex-wrap gap-2">@endunless
    @if (! ($detail ?? false))@if ($inActionMenu ?? false)<flux:menu.item :href="route('central.tenants.show', $tenant)">Informations</flux:menu.item>@else<flux:button :href="route('central.tenants.show', $tenant)">Informations</flux:button>@endif@endif
    @can('update', $tenant)@if ($inActionMenu ?? false)<flux:menu.item :href="route('central.tenants.edit', $tenant)">Modifier</flux:menu.item>@else<flux:button :href="route('central.tenants.edit', $tenant)">Modifier</flux:button>@endif@endcan
    @can('viewHistory', $tenant)@if ($inActionMenu ?? false)<flux:menu.item :href="route('central.tenants.history', $tenant)">Historique</flux:menu.item>@else<flux:button :href="route('central.tenants.history', $tenant)">Historique</flux:button>@endif@endcan
    @if ($detail ?? false)
        @foreach (['suspend' => 'Suspendre', 'activate' => 'Réactiver', 'archive' => 'Archiver'] as $action => $label)
            @can($action, $tenant)
                @if ($action === 'archive' || ($tenant->schema_version !== null && $tenant->provisioned_at !== null
                    && ($action === 'suspend' ? in_array($tenant->status, [\App\Enums\Central\Tenants\StatusEnum::PROVISIONING,
                            \App\Enums\Central\Tenants\StatusEnum::ACTIVE, \App\Enums\Central\Tenants\StatusEnum::INACTIVE,
                            \App\Enums\Central\Tenants\StatusEnum::OVER_QUOTA], true)
                        : in_array($tenant->status, [\App\Enums\Central\Tenants\StatusEnum::INACTIVE,
                            \App\Enums\Central\Tenants\StatusEnum::SUSPENDED, \App\Enums\Central\Tenants\StatusEnum::OVER_QUOTA], true))))
                    <form method="POST" action="{{ route('central.tenants.'.$action, $tenant) }}" class="flex flex-col gap-2"
                        x-data x-on:submit="if (!confirm('Confirmer cette action sur la boutique ?')) $event.preventDefault()">
                        @csrf
                        <input type="hidden" name="expected_status" value="{{ $tenant->status->value }}">
                        <flux:input name="reason" label="Motif facultatif" />
                        @if ($inActionMenu ?? false)<flux:menu.item as="button" type="submit" class="self-start" :variant="$action === 'archive' ? 'danger' : 'outline'">{{ $label }}</flux:menu.item>@else<flux:button type="submit" class="self-start" :variant="$action === 'archive' ? 'danger' : 'outline'">{{ $label }}</flux:button>@endif
                    </form>
                @endif
            @endcan
        @endforeach
    @endif
@unless ($inActionMenu ?? false)</div>@endunless
