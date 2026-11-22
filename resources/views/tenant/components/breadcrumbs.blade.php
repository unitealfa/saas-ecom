@php
    $routeName = request()->route()?->getName() ?? '';
    $actor = auth('tenant')->user();
    $gate = \Illuminate\Support\Facades\Gate::forUser($actor);
    $sections = [
        'tenant.admin.users.' => ['Équipe', 'tenant.admin.users.index', $gate->allows('viewAny', \App\Models\Tenant\Users\User::class)],
        'tenant.admin.team.invitations.' => ['Invitations', 'tenant.admin.team.invitations.index', false],
        'tenant.admin.roles.' => ['Profils et rôles', 'tenant.admin.roles.index', $gate->allows('viewAny', \App\Models\Tenant\Roles\Role::class)],
        'tenant.admin.permissions.' => ['Permissions', 'tenant.admin.permissions.index', $gate->allows('viewAny', \App\Models\Tenant\Permissions\Permission::class)],
        'tenant.admin.shop.addresses.' => ['Adresses', 'tenant.admin.shop.addresses.index', true],
        'tenant.admin.shop.social-links.' => ['Réseaux sociaux', 'tenant.admin.shop.social-links.index', true],
        'tenant.admin.shop.profile' => ['Profil de la boutique', 'tenant.admin.shop.profile', false],
    ];
    $crumbs = [];
    foreach ($sections as $prefix => [$label, $index, $canViewIndex]) {
        if (! str_starts_with($routeName, $prefix)) continue;
        $isIndex = $routeName === $index;
        $crumbs[] = ['label' => $label, 'url' => ! $isIndex && $canViewIndex ? route($index, absolute: false) : null];
        if (! $isIndex) {
            $action = substr($routeName, strlen($prefix));
            $crumbs[] = ['label' => match (true) {
                str_contains($action, 'roles') => 'Rôles',
                str_contains($action, 'permissions') => 'Permissions',
                str_contains($action, 'create') => 'Créer',
                str_contains($action, 'edit') => 'Modifier',
                default => 'Détails',
            }, 'url' => null];
        }
        break;
    }
@endphp

@if ($crumbs !== [])
    <nav aria-label="Fil d’Ariane" class="mb-5 max-w-full overflow-x-auto">
        <flux:breadcrumbs class="min-w-max">
            <flux:breadcrumbs.item :href="route('tenant.dashboard', absolute: false)">Tableau de bord</flux:breadcrumbs.item>
            @foreach ($crumbs as $crumb)
                <flux:breadcrumbs.item :href="$crumb['url']" :aria-current="$loop->last ? 'page' : null">{{ $crumb['label'] }}</flux:breadcrumbs.item>
            @endforeach
        </flux:breadcrumbs>
    </nav>
@endif
