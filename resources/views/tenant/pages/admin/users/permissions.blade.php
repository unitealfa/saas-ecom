@php($shopName = \App\Models\Tenant\Shop\Shop::sole()->shop_name)
<x-tenant::layouts.app title="Droits individuels" :shop-name="$shopName">
    <div class="mx-auto max-w-5xl space-y-5"><flux:heading size="xl">Droits individuels — {{ $member->email }}</flux:heading>
        <p>Les droits effectifs sont individuels : retirer un droit ne supprime pas le profil du membre.</p>
        @if(session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if($errors->any())@foreach($errors->all() as $error)<p class="text-red-500">{{ $error }}</p>@endforeach@endif
        @foreach($catalog as $permission)
            @php($grant = $grants->get($permission->uuid))
            <div class="rounded border p-3"><strong>{{ $permission->label }}</strong><small class="block text-zinc-500">{{ $permission->name }}</small>
                <p>{{ $grant ? 'Attribué jusqu’au '.$grant->pivot->expires_at : 'Non attribué' }}</p>
                @can('managePermissions', $member)
                    <form method="POST" action="{{ route('tenant.admin.users.permissions.change', $member, false) }}" class="flex flex-wrap items-center gap-2">
                        @csrf <input type="hidden" name="permission" value="{{ $permission->uuid }}"><input type="hidden" name="expected_state" value="{{ $state }}">
                        <input name="days" type="number" min="1" max="9999" value="365" class="w-24 rounded border p-2 text-zinc-900" title="Durée en jours">
                        <input name="reason" required maxlength="1000" placeholder="Motif obligatoire" class="rounded border p-2 text-zinc-900">
                        @if($grant)
                            <button name="action" value="revoke" class="rounded border p-2">Retirer</button>
                            @can('renewPermission', $member)<button name="action" value="renew" class="rounded border p-2">Renouveler</button>@endcan
                        @else <button name="action" value="grant" class="rounded border p-2">Attribuer</button> @endif
                    </form>
                @endcan
            </div>
        @endforeach
    </div>
</x-tenant::layouts.app>
