<x-layouts::app title="Informations du bureau">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $point->name }}</flux:heading>
        @if (session('status'))<flux:text role="status">{{ session('status') }}</flux:text>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <dl class="space-y-3">
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
        </dl>
        <flux:text>La disponibilité réelle dépend aussi du transporteur, des zones et du contrat de la boutique. Cette fiche ne garantit pas à elle seule qu’un envoi est possible.</flux:text>
        @include('central.pages.admin.shipping.pickup-points.actions')
        <flux:button :href="route('central.pickup-points.index')">Retour aux bureaux</flux:button>
    </section>
</x-layouts::app>
