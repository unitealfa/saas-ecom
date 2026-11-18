@php($shopName = \App\Models\Tenant\Shop\Shop::sole()->shop_name)
<x-tenant::layouts.app title="Détails du profil" :shop-name="$shopName">
    <div class="mx-auto max-w-4xl space-y-5">
        <flux:heading size="xl">{{ $role->label }}</flux:heading>
        @if(session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        <p>Code : {{ $role->name }} — Version : {{ $role->permission_version }}</p>
        <h2 class="font-semibold">Composition du profil</h2>
        <ul class="list-disc pl-6">@foreach($role->permissionComposition()->get() as $permission)<li>{{ $permission->label }} — {{ $permission->pivot->duration_days }} jours</li>@endforeach</ul>
        <h2 class="font-semibold">Membres affectés</h2>
        <ul class="list-disc pl-6">@forelse($members as $member)<li>{{ $member->email }}</li>@empty<li>Aucun membre</li>@endforelse</ul>
        {{ $members->links() }}
        @can('update', $role) <a class="underline" href="{{ route('tenant.admin.roles.edit', $role, false) }}">Modifier</a> @endcan
        @can('delete', $role)
            <form method="POST" action="{{ route('tenant.admin.roles.destroy', $role, false) }}">
                @csrf @method('DELETE')
                <input type="hidden" name="expected_state" value="{{ app(\App\Services\Tenant\Roles\RoleManager::class)->state($role) }}">
                <button class="rounded border border-red-500 p-2 text-red-500">Supprimer si inutilisé</button>
            </form>
        @endcan
    </div>
</x-tenant::layouts.app>
