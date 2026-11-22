@unless ($inActionMenu ?? false)<div class="flex flex-wrap gap-2">@endunless
    @can('view', $point)@if ($inActionMenu ?? false)<flux:menu.item :href="route('central.pickup-points.show', $point)">Informations</flux:menu.item>@else<flux:button :href="route('central.pickup-points.show', $point)">Informations</flux:button>@endif@endcan
    @can('update', $point)@if ($inActionMenu ?? false)<flux:menu.item :href="route('central.pickup-points.edit', $point)">Modifier</flux:menu.item>@else<flux:button :href="route('central.pickup-points.edit', $point)">Modifier</flux:button>@endif@endcan
    @can('activate', $point)
        <form method="POST" action="{{ route('central.pickup-points.activate', $point) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="expected_state" value="{{ $point->managementState() }}">
            <input type="hidden" name="is_carrier_active" value="{{ $point->is_carrier_active ? '0' : '1' }}">
            @if ($inActionMenu ?? false)<flux:menu.item as="button" type="submit">{{ $point->is_carrier_active ? 'Désactiver' : 'Activer' }}</flux:menu.item>@else<flux:button type="submit">{{ $point->is_carrier_active ? 'Désactiver' : 'Activer' }}</flux:button>@endif
        </form>
    @endcan
    @can('archive', $point)
        <form method="POST" action="{{ route('central.pickup-points.archive', $point) }}" x-on:submit="if (!confirm('Archiver ce bureau ? Les anciennes références seront conservées.')) $event.preventDefault()">
            @csrf @method('DELETE')
            <input type="hidden" name="expected_state" value="{{ $point->managementState() }}">
            @if ($inActionMenu ?? false)<flux:menu.item as="button" type="submit" variant="danger">Archiver</flux:menu.item>@else<flux:button type="submit" variant="danger">Archiver</flux:button>@endif
        </form>
    @endcan
@unless ($inActionMenu ?? false)</div>@endunless
