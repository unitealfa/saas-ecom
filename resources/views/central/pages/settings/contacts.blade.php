<x-layouts::app :title="'Coordonnées de '.$target->name">
    <flux:heading level="1">Coordonnées de {{ $target->name }}</flux:heading>
    @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
    @if (session('warning'))<flux:callout variant="warning">{{ session('warning') }}</flux:callout>@endif
    @foreach ($errors->all() as $error)<flux:callout variant="danger">{{ $error }}</flux:callout>@endforeach
    @if ($available)
        @include('central.partials.user-contacts')
    @else
        <flux:callout variant="warning">La gestion des coordonnées attend la correction des conflits existants et la migration centrale.</flux:callout>
    @endif
</x-layouts::app>
