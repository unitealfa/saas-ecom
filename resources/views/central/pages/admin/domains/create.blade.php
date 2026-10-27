<x-layouts::app :title="__('Ajouter un domaine')">
    <section class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Ajouter un domaine à une boutique</flux:heading>
        @include('central.pages.admin.domains.messages')
        <form method="GET" action="{{ route('central.domains.create') }}" class="flex items-end gap-3">
            @if (request('tenant_uuid'))<input type="hidden" name="tenant_uuid" value="{{ request('tenant_uuid') }}">@endif
            <flux:input name="search" label="Trouver une boutique" :value="request('search')" placeholder="Nom de la boutique" />
            <flux:button type="submit">Rechercher</flux:button>
        </form>
        <form method="POST" action="{{ route('central.domains.store') }}" class="flex flex-col gap-5">
            @csrf
            <flux:select name="tenant_uuid" label="Boutique" required>
                <option value="">Choisissez une boutique</option>
                @foreach ($shops as $shop)
                    <option value="{{ $shop->uuid }}" @selected(old('tenant_uuid', request('tenant_uuid')) === $shop->uuid)>{{ $shop->shop_name }} — {{ $shop->slug }}</option>
                @endforeach
            </flux:select>
            <flux:input name="domain" label="Nom de domaine" :value="old('domain')" placeholder="boutique.exemple.com" required />
            <flux:text>Sans https://, chemin ni port. Le domaine sera ajouté en attente de vérification, sans modifier l’adresse principale ni créer de nouvelle boutique.</flux:text>
            <flux:button type="submit" variant="primary">Ajouter le domaine</flux:button>
        </form>
        {{ $shops->links() }}
        @can('viewAny', \App\Models\Central\Domains\Domain::class)
            <flux:button :href="route('central.domains.index')">Retour aux domaines</flux:button>
        @endcan
    </section>
</x-layouts::app>
