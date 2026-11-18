<x-layouts::app :title="__('Modifier la boutique')">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Modifier la boutique</h1><p class="aydra-muted text-sm sm:text-base">{{ $tenant->shop_name }}</p></div><flux:button :href="route('central.tenants.show', $tenant)" icon="arrow-left">Retour à la boutique</flux:button></header>
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <form method="POST" action="{{ route('central.tenants.update', $tenant) }}" class="aydra-users-panel flex flex-col gap-4 p-5 sm:p-6">
            @csrf @method('PUT')
            <input type="hidden" name="expected_profile_version" value="{{ $tenant->profile_version }}">
            <flux:input name="shop_name" label="Nom public" :value="old('shop_name', $tenant->shop_name)" required />
            <flux:input name="internal_label" label="Libellé interne" :value="old('internal_label', $tenant->internal_label)" required />
            <flux:text>Le propriétaire, le slug, les domaines et le nom de la base sont conservés. Le nouveau nom public sera projeté dans le profil de cette boutique.</flux:text>
            <flux:button type="submit" variant="primary" class="self-start">Enregistrer</flux:button>
        </form>
    </section>
</x-layouts::app>
