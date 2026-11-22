@php($shopName = \App\Models\Tenant\Shop\Shop::sole()->shop_name)
<x-tenant::layouts.app title="Membre de l'équipe" :shop-name="$shopName">
    <div class="mx-auto max-w-4xl space-y-5">
        <flux:heading size="xl">{{ $member->last_name }} {{ $member->first_name }}</flux:heading>
        <p>{{ $member->email }} — {{ $member->membership_status->name }}</p>
        @if(session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        <nav class="flex gap-3 underline">
            @can('viewAny', \App\Models\Tenant\Roles\Role::class)<a href="{{ route('tenant.admin.users.roles', $member, false) }}">Profils</a>@endcan
            @can('viewAny', \App\Models\Tenant\Permissions\Permission::class)<a href="{{ route('tenant.admin.users.permissions', $member, false) }}">Droits individuels</a>@endcan
        </nav>
        @can('manageMembership', $member)
            <form method="POST" action="{{ route('tenant.admin.users.update', $member, false) }}" class="space-y-3 rounded border p-4">
                @csrf @method('PATCH') <input type="hidden" name="expected_state" value="{{ $state }}">
                <label class="block">Nom <input name="last_name" required value="{{ $member->last_name }}" class="rounded border p-2 text-zinc-900"></label>
                <label class="block">Prénom <input name="first_name" value="{{ $member->first_name }}" class="rounded border p-2 text-zinc-900"></label>
                <div class="flex gap-3"><button name="action" value="update" class="rounded border p-2">Modifier</button><button name="action" value="suspend" class="rounded border p-2">Suspendre</button><button name="action" value="archive" class="rounded border border-red-500 p-2">Archiver</button></div>
            </form>
        @endcan
        @if($errors->any()) @foreach($errors->all() as $error)<p class="text-red-500">{{ $error }}</p>@endforeach @endif
    </div>
</x-tenant::layouts.app>
