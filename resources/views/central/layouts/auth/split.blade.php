<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="aydra-login min-h-screen antialiased">
        <div class="aydra-login-layout">
            <main class="aydra-login-main">
                <div class="aydra-login-content">
                    <a href="{{ route('home') }}" class="aydra-login-logo" aria-label="Aydra — accueil" wire:navigate>
                        <img src="{{ asset('images/brand/aydra-logo-light.svg') }}" alt="Aydra" class="h-10 w-auto dark:hidden">
                        <img src="{{ asset('images/brand/aydra-logo-dark.svg') }}" alt="Aydra" class="hidden h-10 w-auto dark:block">
                    </a>
                    {{ $slot }}
                </div>
                <p class="aydra-login-footer">&copy; {{ now()->year }} Aydra</p>
            </main>
            <aside class="aydra-login-aside" aria-label="Aydra">
                <div class="aydra-login-aside-content">
                    <img src="{{ asset('images/brand/aydra-mark-compact.svg') }}" alt="" class="aydra-login-mark">
                    <div>
                        <p class="aydra-login-aside-name">Aydra</p>
                        <h2>Votre commerce, au même endroit.</h2>
                        <p>Retrouvez votre espace pour gérer votre activité et vos boutiques.</p>
                    </div>
                </div>
            </aside>
        </div>

        @persist('toast')
            <flux:toast.group><flux:toast /></flux:toast.group>
        @endpersist
        @fluxScripts
    </body>
</html>
