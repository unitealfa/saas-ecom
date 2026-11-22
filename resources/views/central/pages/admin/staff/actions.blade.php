@can('updateStaff', $user)
    @if ($inActionMenu ?? false)<flux:menu.item :href="route('central.staff.edit', $user)">Modifier</flux:menu.item>@else<flux:button :href="route('central.staff.edit', $user)">Modifier</flux:button>@endif
@endcan
@can('updateStaffPermissions', $user)
    @if ($inActionMenu ?? false)<flux:menu.item :href="route('central.staff.permissions.index', $user)">Modifier les permissions</flux:menu.item>@else<flux:button :href="route('central.staff.permissions.index', $user)">Modifier les permissions</flux:button>@endif
@elsecan('viewStaffPermissions', $user)
    @if ($inActionMenu ?? false)<flux:menu.item :href="route('central.staff.permissions.index', $user)">Permissions effectives</flux:menu.item>@else<flux:button :href="route('central.staff.permissions.index', $user)">Permissions effectives</flux:button>@endif
@endcan
@can('resendStaffInvitation', $user)
    <form method="POST" action="{{ route('central.staff.invitation', $user) }}">
        @csrf
        @if ($inActionMenu ?? false)<flux:menu.item as="button" type="submit">Renvoyer l’invitation</flux:menu.item>@else<flux:button type="submit">Renvoyer l’invitation</flux:button>@endif
    </form>
@endcan
@foreach (['suspendStaff' => [\App\Enums\Central\Users\StatusEnum::SUSPENDED->value, 'Suspendre'], 'activateStaff' => [\App\Enums\Central\Users\StatusEnum::ACTIVE->value, 'Réactiver']] as $ability => [$value, $label])
    @can($ability, $user)
        <form method="POST" action="{{ route('central.staff.status', $user) }}" x-data x-on:submit="if (! window.confirm('Confirmer ce changement d’accès ?')) $event.preventDefault()">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="{{ $value }}">
            @if ($inActionMenu ?? false)<flux:menu.item as="button" type="submit">{{ $label }}</flux:menu.item>@else<flux:button type="submit">{{ $label }}</flux:button>@endif
        </form>
    @endcan
@endforeach
@can('archiveStaff', $user)
    <form method="POST" action="{{ route('central.staff.destroy', $user) }}" x-data x-on:submit="if (! window.confirm('Archiver cet administrateur ?')) $event.preventDefault()">
        @csrf @method('DELETE')
        @if ($inActionMenu ?? false)<flux:menu.item as="button" type="submit" variant="danger">Archiver</flux:menu.item>@else<flux:button type="submit" variant="danger">Archiver</flux:button>@endif
    </form>
@endcan
