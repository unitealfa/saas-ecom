<x-layouts::app title="Rôles du personnel">
    <section class="mx-auto flex max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Rôles de {{ $user->name }}</flux:heading>
        <flux:text>Les permissions et durées sont définies dans chaque rôle Spatie. Garder un rôle conserve sa date d’attribution.</flux:text>
        @if ($errors->any())<flux:callout variant="danger">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <form method="POST" action="{{ route('central.staff.roles.update', $user) }}" class="flex flex-col gap-4">
            @csrf @method('PUT')
            @foreach ($roles as $role)
                <flux:checkbox name="roles[]" :value="$role->uuid" :label="$role->label" :checked="in_array($role->uuid, old('roles', $selectedRoles), true)" />
            @endforeach
            <flux:text>Retirer le dernier rôle retire le compte de cette liste du personnel. Il reste consultable dans Utilisateurs. Le Super Admin est protégé.</flux:text>
            <flux:button type="submit" variant="primary">Enregistrer les rôles</flux:button>
            <flux:button :href="route('central.staff.show', $user)">Retour à la fiche</flux:button>
        </form>
    </section>
</x-layouts::app>
