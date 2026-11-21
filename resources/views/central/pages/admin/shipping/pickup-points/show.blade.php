<x-layouts::app title="Informations du bureau">
    <section class="mx-auto flex w-full max-w-screen-xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">{{ $point->name }}</h1><div class="flex flex-wrap items-center gap-2"><span class="aydra-muted text-sm">{{ $point->carrier->name }} · {{ $point->external_code }}</span><flux:badge size="sm" :color="$point->trashed() ? 'zinc' : ($point->is_carrier_active ? 'green' : 'amber')">{{ $point->trashed() ? 'Archivé' : ($point->is_carrier_active ? 'Actif' : 'Inactif') }}</flux:badge></div></div><flux:button :href="route('central.pickup-points.index')" icon="arrow-left">Retour aux bureaux</flux:button></header>
        @if (session('status'))<flux:callout role="status">{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading text-lg font-semibold">Informations du bureau</h2><dl class="aydra-detail-grid mt-6 grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
            <div><dt>Transporteur</dt><dd>{{ $point->carrier->name }}</dd></div>
            <div><dt>Code chez le transporteur</dt><dd>{{ $point->external_code }}</dd></div>
            <div><dt>Wilaya / commune</dt><dd>{{ $point->province->name_fr }} / {{ $point->municipality?->name_fr ?? 'Non précisée' }}</dd></div>
            <div><dt>Adresse</dt><dd>{{ $point->address }}</dd></div>
            <div><dt>Téléphone public</dt><dd>{{ $point->phone ?? 'Non renseigné' }}</dd></div>
            <div><dt>Carte</dt><dd>@if ($point->map_url && \Illuminate\Support\Str::isUrl($point->map_url, ['http', 'https']))<a href="{{ $point->map_url }}" target="_blank" rel="noopener noreferrer">Ouvrir la carte</a>@else Non renseignée @endif</dd></div>
            <div><dt>État</dt><dd>{{ $point->trashed() ? 'Archivé' : ($point->is_carrier_active ? 'Actif' : 'Inactif') }}</dd></div>
            <div><dt>Source contrôlée</dt><dd>{{ $point->reference_source }}</dd></div>
            <div><dt>Version</dt><dd>{{ $point->reference_version }}</dd></div>
            <div><dt>Dernier import modifiant la fiche</dt><dd>{{ $point->synced_at?->format('d/m/Y H:i') ?? 'Aucun' }}</dd></div>
        </dl></section>
        <flux:text>La disponibilité réelle dépend aussi du transporteur, des zones et du contrat de la boutique. Cette fiche ne garantit pas à elle seule qu’un envoi est possible.</flux:text>
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading mb-4 text-lg font-semibold">Actions</h2>@include('central.pages.admin.shipping.pickup-points.actions')</section>
    </section>
</x-layouts::app>
