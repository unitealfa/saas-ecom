@php($shopName = \App\Models\Tenant\Shop\Shop::sole()->shop_name)
<x-tenant::layouts.app title="Catalogue des permissions" :shop-name="$shopName">
    <div class="mx-auto max-w-4xl space-y-4">
        <flux:heading size="xl">Permissions disponibles</flux:heading>
        <p>Catalogue consultatif : les codes sont définis dans l'application et non créés depuis cette page.</p>
        <form method="GET"><input name="search" value="{{ request('search') }}" placeholder="Chercher" class="rounded border p-2 text-zinc-900"><button class="rounded border p-2">Rechercher</button></form>
        <div class="divide-y rounded border p-4">@foreach($permissions as $permission)<div class="py-3"><strong>{{ $permission->label }}</strong><p class="text-sm text-zinc-500">{{ $permission->name }}</p></div>@endforeach</div>
        {{ $permissions->links() }}
    </div>
</x-tenant::layouts.app>
