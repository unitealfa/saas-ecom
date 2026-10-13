@can('updateStaff', $user)
    <flux:button :href="route('central.staff.edit', $user)">Modifier</flux:button>
@endcan
@can('assignStaffRoles', $user)
    <flux:button :href="route('central.staff.roles.edit', $user)">Rôles</flux:button>
@endcan
@can('viewStaffPermissions', $user)
    <flux:button :href="route('central.staff.permissions.index', $user)">Permissions effectives</flux:button>
@endcan
@can('resendStaffInvitation', $user)
    <form method="POST" action="{{ route('central.staff.invitation', $user) }}">
        @csrf
        <flux:button type="submit">Renvoyer l’invitation</flux:button>
    </form>
@endcan
@foreach (['suspendStaff' => [\App\Enums\Central\Users\StatusEnum::SUSPENDED->value, 'Suspendre'], 'activateStaff' => [\App\Enums\Central\Users\StatusEnum::ACTIVE->value, 'Réactiver']] as $ability => [$value, $label])
    @can($ability, $user)
        <form method="POST" action="{{ route('central.staff.status', $user) }}" x-data x-on:submit="if (! window.confirm('Confirmer ce changement d’accès ?')) $event.preventDefault()">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="{{ $value }}">
            <flux:button type="submit">{{ $label }}</flux:button>
        </form>
    @endcan
@endforeach
@can('archiveStaff', $user)
    <form method="POST" action="{{ route('central.staff.destroy', $user) }}" x-data x-on:submit="if (! window.confirm('Archiver cet administrateur ?')) $event.preventDefault()">
        @csrf @method('DELETE')
        <flux:button type="submit" variant="danger">Archiver</flux:button>
    </form>
@endcan
