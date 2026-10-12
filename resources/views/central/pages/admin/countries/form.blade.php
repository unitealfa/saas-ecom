@if ($errors->any())
    <flux:callout variant="danger" role="alert">
        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    </flux:callout>
@endif
<form method="POST" action="{{ $country ? route('central.countries.update', $country) : route('central.countries.store') }}" class="flex flex-col gap-5">
    @csrf
    @if ($country) @method('PUT') @endif
    <flux:input name="code" label="Code ISO du pays (exemple : DZ)" :value="old('code', $country?->code)" required />
    <flux:input name="name_fr" label="Nom en français" :value="old('name_fr', $country?->name_fr)" required />
    <flux:input name="name_en" label="Nom en anglais" :value="old('name_en', $country?->name_en)" required />
    <flux:input name="name_ar" label="Nom en arabe (facultatif)" :value="old('name_ar', $country?->name_ar)" dir="rtl" />
    <flux:text>L’activation est une action séparée. Un nouveau pays reste désactivé jusqu’à son activation.</flux:text>
    <div class="flex flex-wrap gap-3">
        <flux:button type="submit" variant="primary">Enregistrer</flux:button>
        <flux:button :href="route('central.countries.index')">Retour aux pays</flux:button>
    </div>
</form>
