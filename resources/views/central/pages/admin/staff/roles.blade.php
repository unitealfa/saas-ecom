<x-layouts::app title="Rôles du personnel">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="space-y-2"><h1 class="aydra-heading break-words text-3xl font-semibold tracking-tight sm:text-4xl">Rôles de {{ $user->name }}</h1><p class="aydra-muted text-sm sm:text-base">Gérez les profils attribués à ce compte.</p></header>
        <flux:callout>Un nouveau profil attribue ses droits de départ individuellement. Garder un profil ne réattribue aucun droit retiré et conserve les dates existantes.</flux:callout>
        @if ($errors->any())<flux:callout variant="danger">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <form method="POST" action="{{ route('central.staff.roles.update', $user) }}" class="flex flex-col gap-4">
            @csrf @method('PUT')
            <section class="aydra-users-panel flex flex-col gap-4 p-5 sm:p-6" aria-labelledby="assigned-staff-roles">
            <h2 id="assigned-staff-roles" class="aydra-heading text-lg font-semibold">Profils disponibles</h2>
            @foreach ($roles as $role)
                <flux:checkbox name="roles[]" :value="$role->uuid" :label="$role->label" :checked="in_array($role->uuid, old('roles', $selectedRoles), true)" />
            @endforeach
            <flux:text>Retirer le dernier rôle retire le compte de cette liste du personnel. Il reste consultable dans Utilisateurs. Le Super Admin est protégé.</flux:text>
            <flux:text>Seuls les rôles affichés peuvent être retirés ici. Les autres attributions sont conservées.</flux:text>
            </section>
            <div class="flex flex-wrap items-center gap-3">
            <flux:button type="submit" variant="primary">Enregistrer les rôles</flux:button>
            <flux:button :href="route('central.staff.show', $user)">Retour à la fiche</flux:button>
            </div>
        </form>
    </section>
</x-layouts::app>
