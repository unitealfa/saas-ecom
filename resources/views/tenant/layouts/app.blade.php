@props(['title' => null, 'shopName' => null, 'userName' => null])

<x-tenant::layouts.app.sidebar :title="$title" :shop-name="$shopName" :user-name="$userName">
    <x-slot:navigation>
        {{ $navigation ?? '' }}
    </x-slot:navigation>

    {{ $slot }}
</x-tenant::layouts.app.sidebar>
