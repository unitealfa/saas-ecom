<x-tenant::layouts.app :title="__('Tableau de bord')" :shop-name="$dashboard->shopName" :user-name="$dashboard->userName">
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <flux:heading size="xl">{{ __('Bonjour :name', ['name' => $dashboard->userName]) }}</flux:heading>
            <flux:text>{{ $dashboard->shopName }}</flux:text>
        </div>

        @php($tenantActor = auth('tenant')->user())
        @if ($tenantActor instanceof \App\Models\Tenant\Users\User
            && \App\Services\Tenant\Authorization\TenantAccess::isOwner($tenantActor))
            @if (! \App\Services\Tenant\Authorization\TenantAuthorizationMode::individualOnly())
                <flux:callout variant="warning">
                    La gestion des rôles et de l’équipe nécessite la conversion contrôlée des autorisations de cette boutique.
                    Demandez à l’administrateur technique le diagnostic tenant:authorization-upgrade.
                </flux:callout>
            @elseif (! $tenantActor->can('shop.roles.view') || ! $tenantActor->can('shop.users.view'))
                <flux:callout variant="warning">
                    Les permissions de gestion de l’équipe ne sont pas toutes attribuées à ce compte.
                    Une vérification explicite est nécessaire avant toute réattribution.
                </flux:callout>
            @endif
        @endif

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
