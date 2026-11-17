<x-tenant::layouts.app :title="__('Tableau de bord')" :shop-name="$dashboard->shopName" :user-name="$dashboard->userName">
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <flux:heading size="xl">{{ __('Bonjour :name', ['name' => $dashboard->userName]) }}</flux:heading>
            <flux:text>{{ $dashboard->shopName }}</flux:text>
        </div>

        <div class="flex flex-wrap gap-3">
            @can('viewAny', \App\Models\Tenant\Roles\Role::class)
                <flux:button :href="route('tenant.admin.roles.index', absolute: false)">
                    Gérer les rôles
                </flux:button>
            @endcan

            @can('viewAny', \App\Models\Tenant\Permissions\Permission::class)
                <flux:button :href="route('tenant.admin.permissions.index', absolute: false)">
                    Voir les permissions
                </flux:button>
            @endcan

            @can('viewAny', \App\Models\Tenant\Users\User::class)
                <flux:button :href="route('tenant.admin.users.index', absolute: false)">
                    Gérer l'équipe
                </flux:button>
            @endcan
        </div>

        @can('view', \App\Models\Tenant\Shop\Shop::sole())
            <flux:button :href="route('tenant.admin.shop.profile', absolute: false)">Profil de la boutique</flux:button>
        @endcan
    </div>
</x-tenant::layouts.app>
