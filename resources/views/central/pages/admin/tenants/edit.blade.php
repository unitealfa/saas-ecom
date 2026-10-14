<x-layouts::app :title="__('Modifier la boutique')">
    <section class="mx-auto flex max-w-3xl flex-col gap-6">
        <flux:heading size="xl" level="1">Modifier {{ $tenant->shop_name }}</flux:heading>
        @if ($errors->any())<flux:callout variant="danger" role="alert">{{ $errors->first() }}</flux:callout>@endif
        <form method="POST" action="{{ route('central.tenants.update', $tenant) }}" class="flex flex-col gap-4">
            @csrf @method('PUT')
            <input type="hidden" name="expected_profile_version" value="{{ $tenant->profile_version }}">
            <flux:input name="shop_name" label="Nom public" :value="old('shop_name', $tenant->shop_name)" required />
            <flux:input name="internal_label" label="Libellé interne" :value="old('internal_label', $tenant->internal_label)" required />
            <flux:text>Le propriétaire, le slug, les domaines et le nom de la base sont conservés. Le nouveau nom public sera projeté dans le profil de cette boutique.</flux:text>
            <flux:button type="submit" variant="primary">Enregistrer</flux:button>
        </form>
        <flux:button :href="route('central.tenants.show', $tenant)">Retour à la boutique</flux:button>
    </section>
</x-layouts::app>
