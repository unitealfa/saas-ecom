<x-layouts::app title="Rôles du personnel">
    <section class="mx-auto flex max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Rôles de {{ $user->name }}</flux:heading>
        <flux:text>Un nouveau profil attribue ses droits de départ individuellement. Garder un profil ne réattribue aucun droit retiré et conserve les dates existantes.</flux:text>
        @if ($errors->any())<flux:callout variant="danger">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <form method="POST" action="{{ route('central.staff.roles.update', $user) }}" class="flex flex-col gap-4">
            @csrf @method('PUT')
            @foreach ($roles as $role)
                <flux:checkbox name="roles[]" :value="$role->uuid" :label="$role->label" :checked="in_array($role->uuid, old('roles', $selectedRoles), true)" />
            @endforeach
            <flux:text>Retirer le dernier rôle retire le compte de cette liste du personnel. Il reste consultable dans Utilisateurs. Le Super Admin est protégé.</flux:text>
            <flux:text>Seuls les rôles affichés peuvent être retirés ici. Les autres attributions sont conservées.</flux:text>
            <flux:button type="submit" variant="primary">Enregistrer les rôles</flux:button>
            <flux:button :href="route('central.staff.show', $user)">Retour à la fiche</flux:button>
        </form>
    </section>
</x-layouts::app>
