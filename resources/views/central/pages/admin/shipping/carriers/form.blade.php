@if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
<form method="POST" action="{{ $carrier === null ? route('central.carriers.store') : route('central.carriers.update', $carrier) }}" class="aydra-users-panel flex flex-col gap-5 p-5 sm:p-6">
    <h2 class="aydra-heading text-lg font-semibold">Informations du transporteur</h2>
    @csrf
    @if ($carrier !== null)
        @method('PUT')
        <input type="hidden" name="expected_state" value="{{ $carrier->managementState() }}">
    @endif
    <flux:input name="code" label="Code permanent" :value="$carrier?->code ?? old('code')" :readonly="$carrier !== null" placeholder="Ex. : yalidine" required />
    <flux:input name="name" label="Nom affiché" :value="old('name', $carrier?->name)" placeholder="Ex. : Yalidine" required />
    <flux:text>Choisissez un code unique à la création : lettres, chiffres, tirets ou underscores, en commençant par une lettre. Il ne pourra plus changer après l’enregistrement. Un nouveau transporteur est créé inactif, sans connexion API automatique.</flux:text>
    <div class="flex flex-wrap gap-3">
        <flux:button type="submit" variant="primary">Enregistrer</flux:button>
        @can('viewAny', \App\Models\Central\Shipping\ShippingCarrier::class)
            <flux:button :href="route('central.carriers.index')">Retour aux transporteurs</flux:button>
        @endcan
    </div>
</form>
