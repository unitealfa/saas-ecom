@php($shopName = \App\Models\Tenant\Shop\Shop::sole()->shop_name)
<x-tenant::layouts.app title="Profils du membre" :shop-name="$shopName">
    <div class="mx-auto max-w-4xl space-y-5"><flux:heading size="xl">Profils — {{ $member->email }}</flux:heading>
        @if(session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if($errors->any())@foreach($errors->all() as $error)<p class="text-red-500">{{ $error }}</p>@endforeach@endif
        @foreach($roles as $role)
            <div class="rounded border p-3"><strong>{{ $role->label }}</strong> — {{ in_array($role->id, $assigned) ? 'Attribué' : 'Non attribué' }}
                @can('manageRoles', $member)
                    @can('assign', $role)
                        <form method="POST" action="{{ route('tenant.admin.users.roles.change', $member, false) }}">
                            @csrf <input type="hidden" name="role" value="{{ $role->uuid }}"><input type="hidden" name="expected_state" value="{{ $state }}">
                            <button name="action" value="{{ in_array($role->id, $assigned) ? 'remove' : 'add' }}" class="mt-2 rounded border p-2">{{ in_array($role->id, $assigned) ? 'Retirer ce profil' : 'Attribuer ce profil' }}</button>
                        </form>
                    @endcan
                @endcan
            </div>
        @endforeach
    </div>
</x-tenant::layouts.app>
