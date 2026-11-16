@php
    $gate = \Illuminate\Support\Facades\Gate::forUser(auth('central')->user());
    // Chaque entrée conserve la même Policy et les mêmes routes que le menu central existant.
    $sections = [
        'Comptes et accès' => [
            ['Utilisateurs', 'central.users.index', ['central.users.*'], 'viewAny', \App\Models\User::class, 'users'],
            ['Personnel SaaS', 'central.staff.index', ['central.staff.*'], 'viewAnyStaff', \App\Models\User::class, 'user-group'],
            ['Rôles SaaS', 'central.roles.index', ['central.roles.*'], 'viewAny', \App\Models\Central\Roles\Role::class, 'shield-check'],
            ['Permissions', 'central.permissions.index', ['central.permissions.*'], 'viewAny', \App\Models\Central\Permissions\Permission::class, 'key'],
        ],
        'Boutiques' => [
            ['Boutiques', 'central.tenants.index', ['central.tenants.*', 'central.users.tenants.*'], 'viewAny', \App\Models\Central\Tenants\Tenant::class, 'building-storefront'],
            ['Quotas', 'central.quotas.index', ['central.quotas.*'], 'viewAnyQuota', \App\Models\Central\Users\User::class, 'chart-bar'],
            ['Domaines', 'central.domains.index', ['central.domains.*'], 'viewAny', \App\Models\Central\Domains\Domain::class, 'globe-alt'],
            ['Déploiements', 'central.deployments.index', ['central.deployments.*'], 'viewAny', \App\Models\Central\Deployments\TenantSchemaDeployment::class, 'server-stack'],
        ],
        'Catalogue et abonnements' => [
            ['Fonctionnalités', 'central.features.index', ['central.features.*'], 'viewAny', \App\Models\Central\Features\Feature::class, 'squares-2x2'],
            ['Plans et offres', 'central.plan-pages.index', ['central.plan-pages.*'], 'viewAny', \App\Models\Central\Plans\Plan::class, 'rectangle-stack'],
            ['Abonnements', 'central.subscriptions.index', ['central.subscriptions.*'], 'viewAny', \App\Models\Central\Subscriptions\Subscription::class, 'credit-card'],
            ['Demandes d’abonnement', 'central.subscription-requests.index', ['central.subscription-requests.*'], 'viewAny', \App\Models\Central\Subscriptions\SubscriptionRequest::class, 'document-text'],
        ],
        'Facturation SaaS' => [
            ['Configuration', 'central.billing-rules.index', ['central.billing-rules.*'], 'viewAny', \App\Models\Central\Billing\BillingRule::class, 'document-text'],
            ['Compteurs de facturation', 'central.billing-sequences.index', ['central.billing-sequences.*'], 'viewAny', \App\Models\Central\Billing\BillingSequence::class, 'document-text'],
            ['Avoirs SaaS', 'central.credit-notes.index', ['central.credit-notes.*'], 'viewAny', \App\Models\Central\Billing\SaasCreditNote::class, 'document-text'],
            ['Factures SaaS', 'central.invoices.index', ['central.invoices.*'], 'viewAny', \App\Models\Central\Billing\SaasInvoice::class, 'document-text'],
            ['Envois', 'central.deliveries.index', ['central.deliveries.*'], 'viewAny', \App\Models\Central\Billing\SaasDocumentDelivery::class, 'document-text'],
            ['Paiements SaaS', 'central.payments.index', ['central.payments.index', 'central.payments.show'], 'viewAny', \App\Models\Central\Billing\SaasPayment::class, 'banknotes'],
            ['Signalements de paiements', 'central.payments.corrections.index', ['central.payments.corrections.*'], 'viewAny', \App\Models\Central\Billing\SaasPayment::class, 'banknotes'],
        ],
        'Référentiels' => [
            ['Pays', 'central.countries.index', ['central.countries.*'], 'viewAny', \App\Models\Central\Countries\Country::class, 'globe-alt'],
            ['Transporteurs', 'central.carriers.index', ['central.carriers.*'], 'viewAny', \App\Models\Central\Shipping\ShippingCarrier::class, 'truck'],
            ['Bureaux de retrait', 'central.pickup-points.index', ['central.pickup-points.*'], 'viewAny', \App\Models\Central\Shipping\PickupPoint::class, 'map'],
            ['Codes géographiques des transporteurs', 'central.carrier-mappings.index', ['central.carrier-mappings.*'], 'viewAny', \App\Models\Central\Shipping\CarrierGeoMapping::class, 'map'],
            ['Wilayas et communes', 'central.geography.index', ['central.geography.*'], 'viewAny', \App\Models\Central\Geography\GeographicArea::class, 'map'],
        ],
        'Supervision' => [
            ['Exploitation', 'central.operations.index', ['central.operations.*'], ['viewOperations', 'reconcileSubscriptions', 'reconcilePayments'], \App\Models\User::class, 'server-stack'],
            ['Journal d’activité', 'central.activity.index', ['central.activity.*'], 'viewAny', \App\Models\Central\Audit\Activity::class, 'clipboard-document-list'],
            ['Fichiers centraux', 'central.media.index', ['central.media.*'], 'viewAny', \App\Models\Central\Media\Media::class, 'folder'],
        ],
    ];
@endphp

<flux:sidebar.nav>
    <flux:sidebar.item icon="home" :href="route($dashboardRoute)" :current="request()->routeIs($dashboardRoute === 'dashboard' ? 'dashboard' : 'central.admin-dashboard.*')" wire:navigate>Tableau de bord</flux:sidebar.item>
    @foreach ($sections as $heading => $items)
        @php($visibleItems = array_filter($items, fn ($item) => is_array($item[3]) ? $gate->any($item[3], $item[4]) : $gate->allows($item[3], $item[4])))
        @if ($visibleItems !== [])
            <flux:sidebar.group :heading="$heading" class="mt-4">
                @foreach ($visibleItems as [$label, $routeName, $patterns, $ability, $model, $icon])
                    <flux:sidebar.item :icon="$icon" :href="route($routeName)" :current="request()->routeIs(...$patterns)" wire:navigate>{{ $label }}</flux:sidebar.item>
                @endforeach
            </flux:sidebar.group>
        @endif
    @endforeach
</flux:sidebar.nav>
