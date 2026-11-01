<x-layouts::app :title="$carrier->name">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl">{{ $carrier->name }}</flux:heading>
        @if (session('status'))<flux:text role="status">{{ session('status') }}</flux:text>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <dl class="grid gap-4 sm:grid-cols-2">
            <div><dt>Code permanent</dt><dd>{{ $carrier->code }}</dd></div>
            <div><dt>État</dt><dd>{{ $carrier->trashed() ? 'Archivé' : ($carrier->is_active ? 'Actif' : 'Inactif') }}</dd></div>
            <div><dt>Connecteur prévu</dt><dd>{{ $carrier->adapter === \App\Models\Central\Shipping\ShippingCarrier::UNCONFIGURED_ADAPTER ? 'Non configuré' : $carrier->adapter }}</dd></div>
            <div><dt>Adresse API par défaut</dt><dd>{{ $carrier->default_api_url ?? 'Non configurée' }}</dd></div>
            <div><dt>Source du catalogue</dt><dd>{{ $carrier->reference_source }}</dd></div>
            <div><dt>Version du catalogue</dt><dd>{{ $carrier->reference_version }}</dd></div>
        </dl>
        <flux:text>L’activation rend ce transporteur disponible dans le catalogue. Elle ne connecte aucun compte et ne lance aucune livraison. La connexion API sera traitée séparément.</flux:text>
        <div class="flex flex-wrap gap-3">
            @can('viewAny', \App\Models\Central\Shipping\CarrierGeoMapping::class)
                <flux:button :href="route('central.carrier-mappings.index', ['carrier_uuid' => $carrier->uuid])">Correspondances géographiques</flux:button>
            @endcan
            @can('update', $carrier)<flux:button :href="route('central.carriers.edit', $carrier)">Modifier</flux:button>@endcan
            @can('activate', $carrier)
                <form method="POST" action="{{ route('central.carriers.activate', $carrier) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="expected_state" value="{{ $carrier->managementState() }}">
                    <input type="hidden" name="is_active" value="{{ $carrier->is_active ? '0' : '1' }}">
                    <flux:button type="submit">{{ $carrier->is_active ? 'Désactiver' : 'Activer' }}</flux:button>
                </form>
            @endcan
            @can('archive', $carrier)
                <flux:modal.trigger name="archive-carrier"><flux:button variant="danger">Archiver</flux:button></flux:modal.trigger>
                <flux:modal name="archive-carrier">
                    <form method="POST" action="{{ route('central.carriers.archive', $carrier) }}" class="space-y-4">
                        @csrf @method('DELETE')
                        <input type="hidden" name="expected_state" value="{{ $carrier->managementState() }}">
                        <flux:heading>Archiver {{ $carrier->name }} ?</flux:heading>
                        <flux:text>Le code reste réservé. L’archivage est refusé si une zone, un bureau ou un compte boutique utilise ce transporteur.</flux:text>
                        <flux:button type="submit" variant="danger">Confirmer l’archivage</flux:button>
                    </form>
                </flux:modal>
            @endcan
            <flux:button :href="route('central.carriers.index')">Retour aux transporteurs</flux:button>
        </div>
    </section>
</x-layouts::app>
