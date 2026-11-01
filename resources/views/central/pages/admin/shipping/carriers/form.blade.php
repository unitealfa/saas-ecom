@foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
<form method="POST" action="{{ $carrier === null ? route('central.carriers.store') : route('central.carriers.update', $carrier) }}" class="flex flex-col gap-5">
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
