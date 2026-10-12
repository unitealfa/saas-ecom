@if (in_array($user->status, [\App\Enums\Central\Users\StatusEnum::INACTIVE, \App\Enums\Central\Users\StatusEnum::SUSPENDED], true))
    @can('activate', $user)
        <form method="POST" action="{{ route('central.users.status', $user) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="{{ \App\Enums\Central\Users\StatusEnum::ACTIVE->value }}">
            <flux:button type="submit">Réactiver</flux:button>
        </form>
    @endcan
@else
    @can('suspend', $user)
        <form method="POST" action="{{ route('central.users.status', $user) }}" x-data x-on:submit="if (! window.confirm('Suspendre l’accès de ce compte ?')) $event.preventDefault()">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="{{ \App\Enums\Central\Users\StatusEnum::SUSPENDED->value }}">
            <flux:button type="submit">Suspendre</flux:button>
        </form>
    @endcan
@endif
@can('archive', $user)
    <form method="POST" action="{{ route('central.users.destroy', $user) }}" x-data x-on:submit="if (! window.confirm('Archiver ce compte ? Les données historiques et les boutiques seront conservées.')) $event.preventDefault()">
        @csrf @method('DELETE')
        <flux:button type="submit" variant="danger">Archiver</flux:button>
    </form>
@endcan
@can('approve', $user)
    <form method="POST" action="{{ route('central.users.approve', $user) }}" class="inline-block">
        @csrf
        <flux:button type="submit">Approuver</flux:button>
    </form>
@endcan
@can('reject', $user)
    <form method="POST" action="{{ route('central.users.reject', $user) }}" class="inline-block" x-data x-on:submit="if (! window.confirm('Refuser cette demande d’accès ?')) $event.preventDefault()">
        @csrf
        <flux:button type="submit" variant="danger">Refuser</flux:button>
    </form>
@endcan
