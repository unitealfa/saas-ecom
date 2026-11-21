<x-layouts::app :title="$carrier->name">
    <section class="mx-auto flex w-full max-w-screen-xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">{{ $carrier->name }}</h1><div class="flex flex-wrap items-center gap-2"><span class="aydra-muted text-sm">{{ $carrier->code }}</span><flux:badge size="sm" :color="$carrier->trashed() ? 'zinc' : ($carrier->is_active ? 'green' : 'amber')">{{ $carrier->trashed() ? 'Archivé' : ($carrier->is_active ? 'Actif' : 'Inactif') }}</flux:badge></div></div><flux:button :href="route('central.carriers.index')" icon="arrow-left">Retour aux transporteurs</flux:button></header>
        @if (session('status'))<flux:callout role="status">{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading text-lg font-semibold">Informations du transporteur</h2><dl class="aydra-detail-grid mt-6 grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
            <div><dt>Code permanent</dt><dd>{{ $carrier->code }}</dd></div>
            <div><dt>État</dt><dd>{{ $carrier->trashed() ? 'Archivé' : ($carrier->is_active ? 'Actif' : 'Inactif') }}</dd></div>
            <div><dt>Connecteur prévu</dt><dd>{{ $carrier->adapter === \App\Models\Central\Shipping\ShippingCarrier::UNCONFIGURED_ADAPTER ? 'Non configuré' : $carrier->adapter }}</dd></div>
            <div><dt>Adresse API par défaut</dt><dd>{{ $carrier->default_api_url ?? 'Non configurée' }}</dd></div>
            <div><dt>Source du catalogue</dt><dd>{{ $carrier->reference_source }}</dd></div>
            <div><dt>Version du catalogue</dt><dd>{{ $carrier->reference_version }}</dd></div>
        </dl></section>
        <flux:text>L’activation rend ce transporteur disponible dans le catalogue. Elle ne connecte aucun compte et ne lance aucune livraison. La connexion API sera traitée séparément.</flux:text>
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading mb-4 text-lg font-semibold">Actions</h2><div class="flex flex-wrap gap-3">
            @can('viewAny', \App\Models\Central\Shipping\PickupPoint::class)<flux:button :href="route('central.pickup-points.index', ['carrier_uuid' => $carrier->uuid])">Bureaux de retrait</flux:button>@endcan
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
        </div></section>
    </section>
</x-layouts::app>
