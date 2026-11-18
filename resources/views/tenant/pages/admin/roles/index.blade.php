@php($shopName = \App\Models\Tenant\Shop\Shop::sole()->shop_name)
<x-tenant::layouts.app title="Profils locaux" :shop-name="$shopName">
    <div class="mx-auto max-w-5xl space-y-5">
        <flux:heading size="xl">Profils et rôles</flux:heading>
        @if(session('status')) <flux:callout>{{ session('status') }}</flux:callout> @endif
        <div class="flex gap-4">
            <form method="GET"><input name="search" value="{{ request('search') }}" placeholder="Rechercher un profil" class="rounded border p-2 text-zinc-900"><button class="rounded border p-2">Rechercher</button></form>
            @can('create', \App\Models\Tenant\Roles\Role::class)<a class="underline" href="{{ route('tenant.admin.roles.create', absolute: false) }}">Créer un role</a>@endcan
        </div>
        <div class="divide-y rounded border p-4">
            @forelse($roles as $role)
                <div class="flex items-center justify-between gap-4 py-3">
                    <div><a class="underline font-semibold" href="{{ route('tenant.admin.roles.show', $role, false) }}">{{ $role->label }}</a><p class="text-sm text-zinc-500">{{ $role->name }}</p></div>
                    <span class="text-sm">{{ $role->users_count }} membre(s)</span>
                </div>
            @empty <p>Aucun profil.</p> @endforelse
        </div>
        {{ $roles->links() }}
    </div>
</x-tenant::layouts.app>
