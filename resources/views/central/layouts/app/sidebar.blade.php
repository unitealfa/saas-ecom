<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    @can('viewAny', \App\Models\User::class)
                        <flux:sidebar.item :href="route('central.users.index')" :current="request()->routeIs('central.users.*')" wire:navigate>
                            {{ __('Utilisateurs') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('viewAnyStaff', \App\Models\User::class)
                        <flux:sidebar.item :href="route('central.staff.index')" :current="request()->routeIs('central.staff.*')" wire:navigate>
                            {{ __('Personnel SaaS') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('viewAny', \App\Models\Central\Roles\Role::class)
                        <flux:sidebar.item :href="route('central.roles.index')" :current="request()->routeIs('central.roles.*')" wire:navigate>
                            {{ __('Rôles SaaS') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('viewAny', \App\Models\Central\Permissions\Permission::class)
                        <flux:sidebar.item :href="route('central.permissions.index')" :current="request()->routeIs('central.permissions.*')" wire:navigate>
                            {{ __('Permissions') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('viewAny', \App\Models\Central\Tenants\Tenant::class)
                        <flux:sidebar.item :href="route('central.tenants.index')" :current="request()->routeIs('central.tenants.*', 'central.users.tenants.*')" wire:navigate>
                            {{ __('Boutiques') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('viewAny', \App\Models\Central\Countries\Country::class)
                        <flux:sidebar.item :href="route('central.countries.index')" :current="request()->routeIs('central.countries.*')" wire:navigate>
                            {{ __('Pays') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('viewAny', \App\Models\Central\Geography\GeographicArea::class)
                        <flux:sidebar.item :href="route('central.geography.index')" :current="request()->routeIs('central.geography.*')" wire:navigate>
                            {{ __('Wilayas et communes') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('viewAny', \App\Models\Central\Domains\Domain::class)
                        <flux:sidebar.item :href="route('central.domains.index')" :current="request()->routeIs('central.domains.*')" wire:navigate>
                            {{ __('Domaines') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('viewAny', \App\Models\Central\Deployments\TenantSchemaDeployment::class)
                        <flux:sidebar.item :href="route('central.deployments.index')" :current="request()->routeIs('central.deployments.*')" wire:navigate>
                            {{ __('Déploiements') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('viewAny', \App\Models\Central\Features\Feature::class)
                        <flux:sidebar.item :href="route('central.features.index')" :current="request()->routeIs('central.features.*')" wire:navigate>
                            {{ __('Fonctionnalités') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-central::components.desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :avatar="auth()->user()->profile_photo_url"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :src="auth()->user()->profile_photo_url"
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
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
