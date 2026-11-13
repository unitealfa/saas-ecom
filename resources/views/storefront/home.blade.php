<!doctype html>
<html lang="{{ $profile['locale'] }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $profile['shop_name'] }}</title></head>
<body>
    <main>
        <h1>{{ $profile['shop_name'] }}</h1>
        @if ($profile['logo_url'])<img src="{{ $profile['logo_url'] }}" alt="Logo de {{ $profile['shop_name'] }}" width="160">@endif
        <p>{{ $profile['description'] }}</p>
        <a href="{{ route('tenant.login', absolute: false) }}">Administrer ma boutique</a>
    </main>
</body>
</html>
