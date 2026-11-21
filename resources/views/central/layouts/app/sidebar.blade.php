@php($dashboardRoute = \Illuminate\Support\Facades\Gate::any(['viewAdminDashboard', 'viewFinancialDashboard'], \App\Models\User::class) ? 'central.admin-dashboard.index' : 'dashboard')
@php($isStaff = app(\App\Services\Central\Authorization\CentralUserAccess::class)->isStaff(auth('central')->user()))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body @class(['min-h-screen bg-white dark:bg-zinc-800', 'aydra-admin' => $isStaff])>
        <flux:sidebar sticky collapsible="mobile" @class(['border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900', 'aydra-admin-sidebar' => $isStaff])>
            <flux:sidebar.header>
                @if ($isStaff)
                    <a href="{{ route($dashboardRoute) }}" class="aydra-admin-brand" aria-label="Aydra — tableau de bord" wire:navigate>
                        <img src="{{ asset('images/brand/aydra-logo-light.svg') }}" alt="Aydra" class="h-9 w-auto dark:hidden">
                        <img src="{{ asset('images/brand/aydra-logo-dark.svg') }}" alt="Aydra" class="hidden h-9 w-auto dark:block">
                    </a>
                @else
                    <x-app-logo :sidebar="true" href="{{ route($dashboardRoute) }}" wire:navigate />
                @endif
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="$isStaff ? 'Administration' : __('Mon espace')" class="grid">
                    <flux:sidebar.item icon="home" :href="route($dashboardRoute)" :current="request()->routeIs($dashboardRoute === 'dashboard' ? 'dashboard' : 'central.admin-dashboard.*')" wire:navigate>
                        Tableau de bord
                    </flux:sidebar.item>

                    @canany(['viewOperations', 'reconcileSubscriptions', 'reconcilePayments'], \App\Models\User::class)
                        <flux:sidebar.item icon="server" :href="route('central.operations.index')" :current="request()->routeIs('central.operations.*')" wire:navigate>Exploitation</flux:sidebar.item>
                    @endcanany

                    @can('viewAny', \App\Models\User::class)
                        <flux:sidebar.item icon="users" :href="route('central.users.index')" :current="request()->routeIs('central.users.*')" wire:navigate>{{ __('Utilisateurs') }}</flux:sidebar.item>
                    @endcan

                    <x-central::components.admin-navigation sidebar />
                    @can('viewAny', \App\Models\Central\Audit\Activity::class)
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('central.activity.index')" :current="request()->routeIs('central.activity.*')" wire:navigate>Journal d’activité</flux:sidebar.item>
                    @endcan

                    @can('viewAny', \App\Models\Central\Media\Media::class)
                        <flux:sidebar.item icon="folder" :href="route('central.media.index')" :current="request()->routeIs('central.media.*')" wire:navigate>{{ __('Fichiers centraux') }}</flux:sidebar.item>
                    @endcan

                    {{-- Nouveau tableau de bord commerçant : C-157/C-158/C-159/C-162. --}}
                    @can('viewOwn', \App\Models\Central\Billing\SaasInvoice::class)
                        <flux:sidebar.item icon="building-storefront" :href="route('dashboard').'#mes-boutiques'">Mes boutiques</flux:sidebar.item>
                        <flux:sidebar.item icon="document-text" :href="route('owner.billing.index')" :current="request()->routeIs('owner.billing.*')" wire:navigate>Factures</flux:sidebar.item>
                    @endcan

                    @can('purchase', \App\Models\Central\Subscriptions\Subscription::class)
                        <flux:sidebar.item icon="credit-card" :href="route('owner.subscriptions.index')" :current="request()->routeIs('owner.subscriptions.*')" wire:navigate>Mon abonnement et les offres</flux:sidebar.item>
                    @endcan

                </flux:sidebar.group>
            </flux:sidebar.nav>

        </flux:sidebar>

        <!-- Navigation and account controls -->
        <flux:header class="aydra-context-header block!">
            <div class="flex min-h-14 w-full items-center gap-2">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            @if ($isStaff)
                <a href="{{ route($dashboardRoute) }}" class="lg:hidden" aria-label="Aydra — tableau de bord" wire:navigate>
                    <img src="{{ asset('images/brand/aydra-mark-compact.svg') }}" alt="Aydra" class="h-8 w-8 object-contain">
                </a>
            @endif
            <div class="hidden min-w-0 flex-1 lg:block"><x-central::components.admin-navigation /></div>
            <flux:spacer class="lg:hidden" />
            <div class="flex shrink-0 items-center gap-1 rounded-xl border border-zinc-200 bg-zinc-50/70 p-1 dark:border-zinc-700 dark:bg-zinc-800/60">
                <flux:button type="button" variant="ghost" square x-data="{ themeTimer: null }" x-on:click="clearTimeout(themeTimer); document.documentElement.classList.add('aydra-theme-transition'); $flux.appearance = $flux.dark ? 'light' : 'dark'; themeTimer = setTimeout(() => document.documentElement.classList.remove('aydra-theme-transition'), 240)" x-bind:aria-label="$flux.dark ? 'Activer le thème clair' : 'Activer le thème sombre'" x-bind:title="$flux.dark ? 'Activer le thème clair' : 'Activer le thème sombre'" aria-label="Changer le thème">
                    <flux:icon.sun x-show="$flux.dark" x-cloak class="size-5" />
                    <flux:icon.moon x-show="! $flux.dark" x-cloak class="size-5" />
                </flux:button>
                @can('viewAny', \App\Models\Central\Notifications\DatabaseNotification::class)
                    <flux:button variant="ghost" square icon="bell" :href="route('notifications.index')" aria-label="Notifications" title="Notifications" wire:navigate />
                @endcan
            <flux:dropdown position="bottom" align="end" class="border-s border-zinc-200 ps-1 dark:border-zinc-700">
                <flux:profile :avatar="auth()->user()->profile_photo_url" :name="auth()->user()->name" :initials="auth()->user()->initials()" icon-trailing="chevron-down" class="max-w-48 max-lg:[&>span]:hidden" aria-label="Menu du compte" />
                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start sm:text-sm">
                                <flux:avatar :src="auth()->user()->profile_photo_url" :name="auth()->user()->name" :initials="auth()->user()->initials()" />
                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>
                    <flux:menu.separator />
                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full cursor-pointer" data-test="logout-button">{{ __('Log out') }}</flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
            </div>
            </div>
            <div class="lg:hidden"><x-central::components.admin-navigation /></div>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist
        @fluxScripts
    </body>
</html>
