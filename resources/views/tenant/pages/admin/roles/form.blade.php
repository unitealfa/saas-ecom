@php($shopName = \App\Models\Tenant\Shop\Shop::sole()->shop_name)
<x-tenant::layouts.app :title="$role ? 'Modifier un profil' : 'Créer un profil'" :shop-name="$shopName">
    <div class="mx-auto max-w-4xl space-y-5">
        <flux:heading size="xl">{{ $role ? 'Modifier' : 'Créer' }} un profil</flux:heading>
        @if($errors->any()) <div class="rounded border border-red-500 p-3 text-red-500">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        <form method="POST" action="{{ $role ? route('tenant.admin.roles.update', $role, false) : route('tenant.admin.roles.store', absolute: false) }}" class="space-y-5">
            @csrf @if($role) @method('PUT') <input type="hidden" name="expected_state" value="{{ $expected }}"> @endif
            <label class="block">Code local <input required name="name" value="{{ old('name', $role?->name) }}" maxlength="100" pattern="[a-z][a-z0-9-]*" class="mt-1 block w-full rounded border p-2 text-zinc-900"></label>
            <label class="block">Libellé <input required name="label" value="{{ old('label', $role?->label) }}" maxlength="150" class="mt-1 block w-full rounded border p-2 text-zinc-900"></label>
            <p>Choisissez les permissions et leur durée initiale en jours. Les membres déjà affectés ne seront pas modifiés.</p>
            <input type="hidden" name="_selection_form" value="1">
            <div class="divide-y rounded border p-3">
                @foreach($permissions as $permission)
                    <label class="flex items-center gap-3 py-2">
                        <input type="checkbox" name="selected_permissions[]" value="{{ $permission->uuid }}"
                            @checked(in_array($permission->uuid, old('_selection_form') ? old('selected_permissions', []) : array_keys($selected), true))>
                        <span class="flex-1"><strong>{{ $permission->label }}</strong><small class="block text-zinc-500">{{ $permission->name }}</small></span>
                        <input type="number" min="1" max="9999" name="permissions[{{ $permission->uuid }}]"
                            value="{{ old('permissions.'.$permission->uuid, $selected[$permission->uuid] ?? \App\Models\Shared\Authorization\PermissionAssignment::NEVER_EXPIRES_DAYS) }}" aria-label="Durée en jours pour {{ $permission->label }}" class="w-28 rounded border p-2 text-zinc-900">
                    </label>
                @endforeach
            </div>
            <button class="rounded bg-zinc-800 p-3 text-white">Enregistrer le profil</button>
        </form>
    </div>
</x-tenant::layouts.app>
