<div class="flex flex-wrap gap-2">
    @can('view', $point)<flux:button :href="route('central.pickup-points.show', $point)">Informations</flux:button>@endcan
    @can('update', $point)<flux:button :href="route('central.pickup-points.edit', $point)">Modifier</flux:button>@endcan
    @can('activate', $point)
        <form method="POST" action="{{ route('central.pickup-points.activate', $point) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="expected_state" value="{{ $point->managementState() }}">
            <input type="hidden" name="is_carrier_active" value="{{ $point->is_carrier_active ? '0' : '1' }}">
            <flux:button type="submit">{{ $point->is_carrier_active ? 'Désactiver' : 'Activer' }}</flux:button>
        </form>
    @endcan
    @can('archive', $point)
        <form method="POST" action="{{ route('central.pickup-points.archive', $point) }}" x-on:submit="if (!confirm('Archiver ce bureau ? Les anciennes références seront conservées.')) $event.preventDefault()">
            @csrf @method('DELETE')
            <input type="hidden" name="expected_state" value="{{ $point->managementState() }}">
            <flux:button type="submit" variant="danger">Archiver</flux:button>
        </form>
    @endcan
</div>
