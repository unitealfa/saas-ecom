@if (in_array($user->status, [\App\Enums\Users\StatusEnum::INACTIVE, \App\Enums\Users\StatusEnum::SUSPENDED], true))
    @can('restore', $user)
        <form method="POST" action="{{ route('central.users.status', $user) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="{{ \App\Enums\Users\StatusEnum::ACTIVE->value }}">
            <flux:button type="submit">Réactiver</flux:button>
        </form>
    @endcan
@else
    @can('suspend', $user)
        <form method="POST" action="{{ route('central.users.status', $user) }}" x-data x-on:submit="if (! window.confirm('Suspendre l’accès de ce compte ?')) $event.preventDefault()">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="{{ \App\Enums\Users\StatusEnum::SUSPENDED->value }}">
            <flux:button type="submit">Suspendre</flux:button>
        </form>
    @endcan
@endif
@can('delete', $user)
    <form method="POST" action="{{ route('central.users.destroy', $user) }}" x-data x-on:submit="if (! window.confirm('Supprimer ce compte ? Les données historiques et les boutiques seront conservées.')) $event.preventDefault()">
        @csrf @method('DELETE')
        <flux:button type="submit" variant="danger">Supprimer</flux:button>
    </form>
@endcan
