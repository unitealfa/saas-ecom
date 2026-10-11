@props(['title' => null, 'shopName' => null, 'userName' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" :href="route('tenant.home', absolute: false)" />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            @if ($shopName !== null)
                <flux:heading class="break-words px-2">{{ $shopName }}</flux:heading>
            @endif

            <flux:sidebar.nav>
                {{ $navigation ?? '' }}

                <flux:sidebar.item icon="home" :href="route('tenant.home', absolute: false)">
                    {{ __('Voir la boutique') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:spacer />

            @if ($userName !== null)
                <x-tenant::components.account-summary :name="$userName" />
            @endif
        </flux:sidebar>

        <flux:header class="lg:hidden">
            <flux:sidebar.toggle icon="bars-2" inset="left" />
            <flux:spacer />

            @if ($shopName !== null)
                <flux:heading class="truncate">{{ $shopName }}</flux:heading>
            @endif
        </flux:header>

        <flux:main>
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
