@props(['sidebar' => false])

@php
    $icons = [
        'Personnel SaaS' => 'user-group',
        'Boutiques' => 'building-storefront',
        'Transporteurs' => 'truck',
        'Géographie' => 'globe-alt',
        'Plans et offres' => 'rectangle-stack',
        'Facturation' => 'document-text',
        'Abonnements' => 'credit-card',
    ];
    $groups = [
        'Personnel SaaS' => [
            ['label' => 'Personnel', 'route' => 'central.staff.index', 'model' => \App\Models\User::class, 'ability' => 'viewAnyStaff', 'matches' => ['central.staff.*']],
            ['label' => 'Rôles SaaS', 'route' => 'central.roles.index', 'model' => \App\Models\Central\Roles\Role::class, 'ability' => 'viewAny', 'matches' => ['central.roles.*']],
            ['label' => 'Permissions', 'route' => 'central.permissions.index', 'model' => \App\Models\Central\Permissions\Permission::class, 'ability' => 'viewAny', 'matches' => ['central.permissions.*']],
        ],
        'Boutiques' => [
            ['label' => 'Boutiques', 'route' => 'central.tenants.index', 'model' => \App\Models\Central\Tenants\Tenant::class, 'ability' => 'viewAny', 'matches' => ['central.tenants.*', 'central.users.tenants.*']],
            ['label' => 'Domaines', 'route' => 'central.domains.index', 'model' => \App\Models\Central\Domains\Domain::class, 'ability' => 'viewAny', 'matches' => ['central.domains.*']],
            ['label' => 'Déploiements', 'route' => 'central.deployments.index', 'model' => \App\Models\Central\Deployments\TenantSchemaDeployment::class, 'ability' => 'viewAny', 'matches' => ['central.deployments.*']],
        ],
        'Transporteurs' => [
            ['label' => 'Transporteurs', 'route' => 'central.carriers.index', 'model' => \App\Models\Central\Shipping\ShippingCarrier::class, 'ability' => 'viewAny', 'matches' => ['central.carriers.*']],
            ['label' => 'Codes géographiques', 'route' => 'central.carrier-mappings.index', 'model' => \App\Models\Central\Shipping\CarrierGeoMapping::class, 'ability' => 'viewAny', 'matches' => ['central.carrier-mappings.*']],
            ['label' => 'Bureaux de retrait', 'route' => 'central.pickup-points.index', 'model' => \App\Models\Central\Shipping\PickupPoint::class, 'ability' => 'viewAny', 'matches' => ['central.pickup-points.*']],
        ],
        'Géographie' => [
            ['label' => 'Pays', 'route' => 'central.countries.index', 'model' => \App\Models\Central\Countries\Country::class, 'ability' => 'viewAny', 'matches' => ['central.countries.*']],
            ['label' => 'Wilayas et communes', 'route' => 'central.geography.index', 'model' => \App\Models\Central\Geography\GeographicArea::class, 'ability' => 'viewAny', 'matches' => ['central.geography.*']],
        ],
        'Plans et offres' => [
            ['label' => 'Offres', 'route' => 'central.plan-pages.index', 'model' => \App\Models\Central\Plans\Plan::class, 'ability' => 'viewAny', 'matches' => ['central.plan-pages.*']],
            ['label' => 'Fonctionnalités', 'route' => 'central.features.index', 'model' => \App\Models\Central\Features\Feature::class, 'ability' => 'viewAny', 'matches' => ['central.features.*']],
        ],
        'Facturation' => [
            ['label' => 'Factures', 'route' => 'central.invoices.index', 'model' => \App\Models\Central\Billing\SaasInvoice::class, 'ability' => 'viewBillingDossiers', 'matches' => ['central.invoices.*', 'central.payments.*']],
            ['label' => 'Avoirs', 'route' => 'central.credit-notes.index', 'model' => \App\Models\Central\Billing\SaasCreditNote::class, 'ability' => 'viewAny', 'matches' => ['central.credit-notes.*']],
        ],
        'Abonnements' => [
            ['label' => 'Abonnements', 'route' => 'central.subscriptions.index', 'model' => \App\Models\Central\Subscriptions\Subscription::class, 'ability' => 'viewAny', 'matches' => ['central.subscriptions.*']],
            ['label' => 'Demandes', 'route' => 'central.subscription-requests.index', 'model' => \App\Models\Central\Subscriptions\SubscriptionRequest::class, 'ability' => 'viewAny', 'matches' => ['central.subscription-requests.*']],
            ['label' => 'Quotas', 'route' => 'central.quotas.index', 'model' => \App\Models\Central\Users\User::class, 'ability' => 'viewAnyQuota', 'matches' => ['central.quotas.*']],
        ],
    ];
@endphp

@foreach ($groups as $label => $items)
    @php
        $current = collect($items)->contains(fn (array $item): bool => request()->routeIs(...$item['matches']));
    @endphp
    @continue(! $sidebar && ! $current)
    @php
        $visible = collect($items)->filter(fn (array $item): bool => \Illuminate\Support\Facades\Gate::allows($item['ability'], $item['model']))->values();
    @endphp
    @continue($visible->isEmpty())
    @if ($sidebar)
        <flux:sidebar.item :icon="$icons[$label]" :href="route($visible->first()['route'])" :current="$current" wire:navigate>{{ $label }}</flux:sidebar.item>
    @elseif ($current)
        <flux:navbar scrollable aria-label="{{ $label }}" class="aydra-context-nav">
            @foreach ($visible as $item)
                <flux:navbar.item :href="route($item['route'])" :current="request()->routeIs(...$item['matches'])" wire:navigate>{{ $item['label'] }}</flux:navbar.item>
            @endforeach
        </flux:navbar>
    @endif
@endforeach
