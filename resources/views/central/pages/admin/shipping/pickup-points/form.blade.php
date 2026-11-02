@foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
<form method="POST" action="{{ $point === null ? route('central.pickup-points.store') : route('central.pickup-points.update', $point) }}" class="flex flex-col gap-5">
    @csrf
    @if ($point !== null)
        @method('PUT')
        <input type="hidden" name="expected_state" value="{{ $point->managementState() }}">
        <input type="hidden" name="carrier_uuid" value="{{ $point->carrier->uuid }}">
        <input type="hidden" name="province_uuid" value="{{ $point->province->uuid }}">
        <input type="hidden" name="municipality_uuid" value="{{ $point->municipality?->uuid }}">
        <input type="hidden" name="external_code" value="{{ $point->external_code }}">
        <flux:text>Transporteur : {{ $point->carrier->name }} — Code : {{ $point->external_code }} — Wilaya : {{ $point->province->name_fr }} — Commune : {{ $point->municipality?->name_fr ?? 'Non précisée' }}. Ces liens restent permanents pour préserver les références existantes.</flux:text>
    @else
        <flux:select name="carrier_uuid" label="Transporteur" required>
            <option value="">Sélectionner un transporteur</option>
            @foreach ($carriers as $carrier)<option value="{{ $carrier->uuid }}" @selected(old('carrier_uuid', request('carrier_uuid')) === $carrier->uuid)>{{ $carrier->name }}</option>@endforeach
        </flux:select>
        <flux:input name="external_code" label="Code officiel du bureau chez le transporteur" :value="old('external_code')" required />
        <div x-data="{ province: @js(old('province_uuid', '')), municipality: @js(old('municipality_uuid', '')) }" class="space-y-4">
            @include('central.pages.admin.shipping.pickup-points.zone-picker', ['kind' => 1, 'field' => 'province_uuid', 'label' => 'Wilaya', 'required' => true])
            @include('central.pages.admin.shipping.pickup-points.zone-picker', ['kind' => 2, 'field' => 'municipality_uuid', 'label' => 'Commune (facultative)', 'required' => false])
        </div>
    @endif
    <flux:input name="name" label="Nom du bureau" :value="old('name', $point?->name)" required />
    <flux:textarea name="address" label="Adresse publique" required>{{ old('address', $point?->address) }}</flux:textarea>
    <flux:input name="phone" label="Téléphone public (facultatif)" :value="old('phone', $point?->phone)" />
    <flux:input name="map_url" label="Lien de carte (facultatif, HTTP ou HTTPS)" type="url" :value="old('map_url', $point?->map_url)" />
    <flux:input name="reference_source" label="Source officielle contrôlée" :value="old('reference_source', $point?->reference_source)" placeholder="Nom ou lien du document officiel du transporteur" required />
    <flux:text>Renseignez seulement les informations publiques communes au réseau, jamais les identifiants privés d’un compte. Un nouveau bureau est inactif ; l’activation est une action distincte.</flux:text>
    <div class="flex gap-3">
        <flux:button type="submit" variant="primary">Enregistrer</flux:button>
        @can('viewAny', \App\Models\Central\Shipping\PickupPoint::class)<flux:button :href="route('central.pickup-points.index')">Retour aux bureaux</flux:button>@endcan
    </div>
</form>
