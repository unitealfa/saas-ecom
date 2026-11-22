@can('archive', $media)
    <form method="POST" action="{{ route('central.media.archive', $media) }}" x-data x-on:submit="if (!confirm('Archiver ce fichier ? Son historique et ses octets seront conservés.')) $event.preventDefault()">
        @csrf @method('DELETE')
        <input type="hidden" name="expected_state" value="{{ $media->managementState() }}">
        @if ($inActionMenu ?? false)<flux:menu.item as="button" type="submit" variant="danger">Archiver</flux:menu.item>@else<flux:button type="submit" variant="danger">Archiver</flux:button>@endif
    </form>
@endcan
