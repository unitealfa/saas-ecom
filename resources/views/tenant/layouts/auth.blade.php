@props(['title' => null, 'shopName' => null])

<x-tenant::layouts.auth.simple :title="$title" :shop-name="$shopName">
    {{ $slot }}
</x-tenant::layouts.auth.simple>
