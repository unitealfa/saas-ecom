<x-layouts::app :title="__('Attribuer les rôles')">
    <section class="mx-auto flex max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Rôles de {{ $user->name }}</flux:heading>
        <flux:text>Les droits sont accordés par les rôles Spatie. Garder un rôle conserve sa date d’attribution ; les durées sont définies dans le rôle.</flux:text>
        @if ($errors->any())
            <flux:callout variant="danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </flux:callout>
        @endif
        <form method="POST" action="{{ route('central.users.roles.update', $user) }}" class="flex flex-col gap-5">
            @csrf
            @method('PUT')
            @foreach ($roles as $role)
                <flux:checkbox name="roles[]" :value="$role->uuid" :label="$role->label"
                    :checked="in_array($role->uuid, old('roles', $selectedRoles), true)" />
            @endforeach
            <flux:text>Décochez tous les rôles pour retirer les rôles SaaS. Le rôle Super Admin est protégé et ne peut pas être attribué ici.</flux:text>
            <div class="flex flex-wrap gap-3">
                <flux:button type="submit" variant="primary">Enregistrer</flux:button>
                @can('viewAny', \App\Models\User::class)
                    <flux:button :href="route('central.users.index')">Retour aux utilisateurs</flux:button>
                @else
                    <flux:button :href="route('dashboard')">Retour au dashboard</flux:button>
                @endcan
            </div>
        </form>
    </section>
</x-layouts::app>
