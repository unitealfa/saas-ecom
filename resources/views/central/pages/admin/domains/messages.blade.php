@if (session('status'))
    <flux:callout>{{ session('status') }}</flux:callout>
@endif
@if ($errors->any())
    <flux:callout variant="danger" role="alert">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </flux:callout>
@endif
