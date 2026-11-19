<x-layouts::app :title="__('Ajouter un domaine')">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Ajouter un domaine</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">Choisissez une boutique et renseignez l’adresse à vérifier.</p></div>@can('viewAny', \App\Models\Central\Domains\Domain::class)<flux:button :href="route('central.domains.index')" icon="arrow-left">Retour aux domaines</flux:button>@endcan</header>
        @include('central.pages.admin.domains.messages')
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading text-lg font-semibold">Trouver une boutique</h2><p class="aydra-muted mt-1 text-sm">Les suggestions proviennent des boutiques affichées sur cette page.</p><form method="GET" action="{{ route('central.domains.create') }}" class="mt-4 flex flex-wrap items-end gap-3">
            @if (request('tenant_uuid'))<input type="hidden" name="tenant_uuid" value="{{ request('tenant_uuid') }}">@endif
            <flux:input name="search" label="Nom de la boutique" :value="request('search')" type="search" icon="magnifying-glass" list="domain-shop-suggestions" placeholder="Nom de la boutique" class="min-w-0 flex-1 sm:min-w-72" />
            <datalist id="domain-shop-suggestions">@foreach ($shops as $shop)<option value="{{ $shop->shop_name }}"></option>@endforeach</datalist>
            <flux:button type="submit" variant="primary">Rechercher</flux:button><flux:button :href="route('central.domains.create', request()->only('tenant_uuid'))">Effacer</flux:button>
        </form>@if ($shops->hasPages())<div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">{{ $shops->links() }}</div>@endif</section>
        <form method="POST" action="{{ route('central.domains.store') }}" class="aydra-users-panel flex flex-col gap-5 p-5 sm:p-6">
            @csrf
            <h2 class="aydra-heading text-lg font-semibold">Adresse du domaine</h2>
            <flux:select name="tenant_uuid" label="Boutique" required>
                <option value="">Choisissez une boutique</option>
                @foreach ($shops as $shop)
                    <option value="{{ $shop->uuid }}" @selected(old('tenant_uuid', request('tenant_uuid')) === $shop->uuid)>{{ $shop->shop_name }} — {{ $shop->slug }}</option>
                @endforeach
            </flux:select>
            <flux:input name="domain" label="Nom de domaine" :value="old('domain')" placeholder="boutique.exemple.com" required />
            <flux:text>Sans https://, chemin ni port. Le domaine sera ajouté en attente de vérification, sans modifier l’adresse principale ni créer de nouvelle boutique.</flux:text>
            <flux:button type="submit" variant="primary" class="self-start">Ajouter le domaine</flux:button>
        </form>
    </section>
</x-layouts::app>
