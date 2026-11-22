@php
    $routeName = preg_replace('/^central\.alias\.\d+\./', '', request()->route()?->getName() ?? '');
    $gate = \Illuminate\Support\Facades\Gate::forUser(auth('central')->user());
    $dashboardRoute = $gate->any(['viewAdminDashboard', 'viewFinancialDashboard'], \App\Models\User::class) ? 'central.admin-dashboard.index' : 'dashboard';
    // The abilities mirror the corresponding list pages, not the current page's ability.
    $sections = [
        'central.users.tenants.' => ['Boutiques', 'central.tenants.index', 'viewAny', \App\Models\Central\Tenants\Tenant::class],
        'central.users.' => ['Utilisateurs', 'central.users.index', 'viewAny', \App\Models\User::class],
        'central.staff.' => ['Personnel SaaS', 'central.staff.index', 'viewAnyStaff', \App\Models\User::class],
        'central.roles.' => ['Rôles SaaS', 'central.roles.index', 'viewAny', \App\Models\Central\Roles\Role::class],
        'central.permissions.' => ['Permissions', 'central.permissions.index', 'viewAny', \App\Models\Central\Permissions\Permission::class],
        'central.tenants.' => ['Boutiques', 'central.tenants.index', 'viewAny', \App\Models\Central\Tenants\Tenant::class],
        'central.domains.' => ['Domaines', 'central.domains.index', 'viewAny', \App\Models\Central\Domains\Domain::class],
        'central.deployments.' => ['Déploiements', 'central.deployments.index', 'viewAny', \App\Models\Central\Deployments\TenantSchemaDeployment::class],
        'central.carriers.' => ['Transporteurs', 'central.carriers.index', 'viewAny', \App\Models\Central\Shipping\ShippingCarrier::class],
        'central.carrier-mappings.' => ['Codes géographiques', 'central.carrier-mappings.index', 'viewAny', \App\Models\Central\Shipping\CarrierGeoMapping::class],
        'central.pickup-points.' => ['Bureaux de retrait', 'central.pickup-points.index', 'viewAny', \App\Models\Central\Shipping\PickupPoint::class],
        'central.countries.' => ['Pays', 'central.countries.index', 'viewAny', \App\Models\Central\Countries\Country::class],
        'central.geography.' => ['Wilayas et communes', 'central.geography.index', 'viewAny', \App\Models\Central\Geography\GeographicArea::class],
        'central.plan-pages.' => ['Offres', 'central.plan-pages.index', 'viewAny', \App\Models\Central\Plans\Plan::class],
        'central.features.' => ['Fonctionnalités', 'central.features.index', 'viewAny', \App\Models\Central\Features\Feature::class],
        'central.invoices.' => ['Factures', 'central.invoices.index', 'viewBillingDossiers', \App\Models\Central\Billing\SaasInvoice::class],
        'central.credit-notes.' => ['Avoirs', 'central.credit-notes.index', 'viewAny', \App\Models\Central\Billing\SaasCreditNote::class],
        'central.subscription-requests.' => ['Demandes d’abonnement', 'central.subscription-requests.index', 'viewAny', \App\Models\Central\Subscriptions\SubscriptionRequest::class],
        'central.subscriptions.' => ['Abonnements', 'central.subscriptions.index', 'viewAny', \App\Models\Central\Subscriptions\Subscription::class],
        'central.quotas.' => ['Quotas', 'central.quotas.index', 'viewAnyQuota', \App\Models\Central\Users\User::class],
        'central.activity.' => ['Journal d’activité', 'central.activity.index', 'viewAny', \App\Models\Central\Audit\Activity::class],
        'central.media.' => ['Fichiers centraux', 'central.media.index', 'viewAny', \App\Models\Central\Media\Media::class],
    ];
    $section = null;
    $prefix = null;
    foreach ($sections as $candidate => $definition) {
        if (str_starts_with($routeName, $candidate)) {
            $prefix = $candidate;
            $section = $definition;
            break;
        }
    }

    $crumbs = [];
    if ($section) {
        [$label, $index, $ability, $model] = $section;
        $action = substr($routeName, strlen($prefix));
        $isIndex = $routeName === $index;
        $crumbs[] = ['label' => $label, 'url' => ! $isIndex && $gate->allows($ability, $model) ? route($index) : null];
        if (! $isIndex) {
            $detailRoutes = [
                'central.users.index' => ['central.users.show', 'user', 'view'],
                'central.staff.index' => ['central.staff.show', 'user', 'viewStaff'],
                'central.roles.index' => ['central.roles.show', 'role', 'view'],
                'central.tenants.index' => ['central.tenants.show', 'tenant', 'view'],
                'central.geography.index' => ['central.geography.show', 'area', 'view'],
                'central.carriers.index' => ['central.carriers.show', 'carrier', 'view'],
                'central.pickup-points.index' => ['central.pickup-points.show', 'point', 'view'],
                'central.plan-pages.index' => ['central.plan-pages.show', 'plan', 'view'],
                'central.features.index' => ['central.features.show', 'feature', 'view'],
                'central.subscriptions.index' => ['central.subscriptions.show', 'subscription', 'view'],
                'central.quotas.index' => ['central.quotas.show', 'user', 'viewQuota'],
            ];
            if (isset($detailRoutes[$index]) && (str_contains($action, 'edit') || str_contains($action, 'history') || str_contains($action, 'permissions') || str_contains($action, 'change'))) {
                [$detailRoute, $parameter, $detailAbility] = $detailRoutes[$index];
                $target = request()->route($parameter);
                if ($target instanceof \Illuminate\Database\Eloquent\Model && $gate->allows($detailAbility, $target)) {
                    $crumbs[] = ['label' => 'Détails', 'url' => route($detailRoute, $target)];
                }
            }
            $current = match (true) {
                $action === 'pending' => 'Inscriptions en attente',
                $action === 'import' => 'Importer',
                str_contains($action, 'permissions') => 'Permissions',
                str_contains($action, 'roles') => 'Rôles',
                str_contains($action, 'history') => 'Historique',
                str_contains($action, 'change') => 'Changer l’abonnement',
                str_contains($action, 'create') => 'Créer',
                str_contains($action, 'edit') => 'Modifier',
                default => 'Détails',
            };
            $crumbs[] = ['label' => $current, 'url' => null];
        }
    } elseif ($routeName === 'central.operations.index') {
        $crumbs[] = ['label' => 'Exploitation', 'url' => null];
    } elseif ($routeName === 'notifications.index') {
        $crumbs[] = ['label' => 'Notifications', 'url' => null];
    } elseif (in_array($routeName, ['profile.edit', 'security.edit', 'appearance.edit'], true)) {
        $crumbs[] = ['label' => 'Paramètres', 'url' => $routeName !== 'profile.edit' ? route('profile.edit') : null];
        if ($routeName !== 'profile.edit') $crumbs[] = ['label' => $routeName === 'security.edit' ? 'Sécurité' : 'Apparence', 'url' => null];
    } elseif (str_starts_with($routeName, 'owner.')) {
        $ownerLabel = match (true) {
            str_starts_with($routeName, 'owner.subscription-requests.') => 'Demandes d’abonnement',
            str_starts_with($routeName, 'owner.subscriptions.') => 'Abonnements',
            str_starts_with($routeName, 'owner.invoices.') => 'Factures',
            str_starts_with($routeName, 'owner.credit-notes.') => 'Avoirs',
            str_starts_with($routeName, 'owner.payments.') => 'Paiements',
            str_starts_with($routeName, 'owner.billing.') => 'Facturation',
            str_starts_with($routeName, 'owner.shops.') => 'Boutiques',
            default => null,
        };
        $ownerIndex = match (true) {
            str_starts_with($routeName, 'owner.subscription-requests.') => ['owner.subscription-requests.index', 'create', \App\Models\Central\Subscriptions\SubscriptionRequest::class],
            str_starts_with($routeName, 'owner.subscriptions.') => ['owner.subscriptions.index', 'purchase', \App\Models\Central\Subscriptions\Subscription::class],
            str_starts_with($routeName, 'owner.invoices.') => ['owner.invoices.index', 'viewOwn', \App\Models\Central\Billing\SaasInvoice::class],
            str_starts_with($routeName, 'owner.credit-notes.') => ['owner.credit-notes.index', 'viewOwn', \App\Models\Central\Billing\SaasCreditNote::class],
            str_starts_with($routeName, 'owner.payments.') => ['owner.payments.index', 'viewOwn', \App\Models\Central\Billing\SaasPayment::class],
            default => null,
        };
        if ($ownerLabel) {
            $url = $ownerIndex && $routeName !== $ownerIndex[0] && $gate->allows($ownerIndex[1], $ownerIndex[2]) ? route($ownerIndex[0]) : null;
            $crumbs[] = ['label' => $ownerLabel, 'url' => $url];
        }
        if ($routeName === 'owner.shops.domains.index') {
            $crumbs[] = ['label' => 'Domaines', 'url' => null];
        } elseif ($ownerLabel && (str_ends_with($routeName, '.show') || str_ends_with($routeName, '.create'))) {
            $crumbs[] = ['label' => str_ends_with($routeName, '.create') ? 'Créer' : 'Détails', 'url' => null];
        }
    }
@endphp

@if ($crumbs !== [])
    <nav aria-label="Fil d’Ariane" class="mb-5 max-w-full overflow-x-auto">
        <flux:breadcrumbs class="min-w-max">
            <flux:breadcrumbs.item :href="route($dashboardRoute)" wire:navigate>Tableau de bord</flux:breadcrumbs.item>
            @foreach ($crumbs as $crumb)
                <flux:breadcrumbs.item :href="$crumb['url']" :aria-current="$loop->last ? 'page' : null" wire:navigate>{{ $crumb['label'] }}</flux:breadcrumbs.item>
            @endforeach
        </flux:breadcrumbs>
    </nav>
@endif
