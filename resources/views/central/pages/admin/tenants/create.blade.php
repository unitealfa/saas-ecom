<x-layouts::app :title="__('Créer une boutique')">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        @if ($user === null)
            <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Choisir le propriétaire</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">Choisissez un compte commerçant actif. Les comptes du personnel SaaS ne possèdent pas de boutiques.</p></div><flux:button :href="route('central.tenants.index')" icon="arrow-left">Retour aux boutiques</flux:button></header>
            <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading text-lg font-semibold">Rechercher un commerçant</h2><form method="GET" action="{{ route('central.tenants.create') }}" class="mt-4 flex flex-wrap items-end gap-3">
                <flux:input name="search" label="Nom ou e-mail du commerçant" :value="$search" type="search" icon="magnifying-glass" list="tenant-owner-suggestions" class="min-w-0 flex-1 sm:min-w-72" />
                <datalist id="tenant-owner-suggestions">@foreach ($owners as $owner)<option value="{{ $owner->name }}"></option><option value="{{ $owner->email }}"></option>@endforeach</datalist>
                <flux:button type="submit" variant="primary">Rechercher</flux:button><flux:button :href="route('central.tenants.create')">Effacer</flux:button>
            </form><p class="aydra-muted mt-3 text-xs">Les suggestions proviennent des commerçants affichés sur cette page.</p></section>
            <section class="aydra-users-panel"><div class="aydra-users-toolbar"><div><h2 class="aydra-heading text-lg font-semibold">Commerçants</h2><p class="aydra-muted mt-1 text-sm">{{ $owners->total() }} {{ $owners->total() === 1 ? 'compte trouvé' : 'comptes trouvés' }}</p></div></div><div class="aydra-users-table"><flux:table><flux:table.columns><flux:table.column>Nom</flux:table.column><flux:table.column>E-mail</flux:table.column><flux:table.column>Actions</flux:table.column></flux:table.columns><flux:table.rows>
                @forelse ($owners as $owner)<flux:table.row :key="$owner->uuid"><flux:table.cell variant="strong">{{ $owner->name }}</flux:table.cell><flux:table.cell>{{ $owner->email }}</flux:table.cell><flux:table.cell><flux:button :href="route('central.users.tenants.create', $owner)">Choisir ce propriétaire</flux:button></flux:table.cell></flux:table.row>
                @empty <flux:table.row><flux:table.cell colspan="3"><div class="aydra-users-empty">Aucun commerçant actif ne correspond à la recherche.</div></flux:table.cell></flux:table.row> @endforelse
            </flux:table.rows></flux:table>@if ($owners->hasPages())<div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">{{ $owners->links() }}</div>@endif</div></section>
        @else
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Boutiques de {{ $user->name }}</h1><p class="aydra-muted text-sm sm:text-base">Consultez les boutiques existantes et préparez une nouvelle boutique.</p></div><flux:button :href="route('central.tenants.index')" icon="arrow-left">Retour aux boutiques</flux:button></header>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        <flux:text>Chaque boutique possède sa propre base MySQL. Le quota dépend de l’offre du propriétaire ; l’offre gratuite permet deux boutiques. Une préparation en cours ou en échec réserve aussi une place.</flux:text>
        <section class="aydra-users-panel"><div class="aydra-users-toolbar"><div><h2 class="aydra-heading text-lg font-semibold">Boutiques existantes</h2><p class="aydra-muted mt-1 text-sm">{{ $shops->total() }} {{ $shops->total() === 1 ? 'boutique' : 'boutiques' }}</p></div></div><div class="aydra-users-table"><flux:table class="whitespace-normal"><flux:table.columns><flux:table.column>Boutique</flux:table.column><flux:table.column>Domaines</flux:table.column><flux:table.column>État</flux:table.column><flux:table.column>Actions</flux:table.column></flux:table.columns><flux:table.rows>
            @forelse ($shops as $shop)<flux:table.row :key="$shop->uuid"><flux:table.cell variant="strong">{{ $shop->shop_name }}</flux:table.cell><flux:table.cell>{{ $shop->domains->pluck('domain')->join(', ') ?: 'Aucun domaine' }}</flux:table.cell><flux:table.cell><flux:badge size="sm">{{ $shop->status->label() }}</flux:badge></flux:table.cell><flux:table.cell><flux:button :href="route('central.tenants.show', $shop)">Informations</flux:button></flux:table.cell></flux:table.row>
            @empty <flux:table.row><flux:table.cell colspan="4"><div class="aydra-users-empty">Aucune boutique enregistrée.</div></flux:table.cell></flux:table.row> @endforelse
        </flux:table.rows></flux:table>@if ($shops->hasPages())<div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">{{ $shops->links() }}</div>@endif</div></section>
        @if ($errors->any())
            <flux:callout variant="danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </flux:callout>
        @endif
        <form method="POST" action="{{ route('central.users.tenants.store', $user) }}" class="aydra-users-panel flex flex-col gap-5 p-5 sm:p-6">
            <h2 class="aydra-heading text-lg font-semibold">Nouvelle boutique</h2>
            @csrf
            <input type="hidden" name="creation_key" value="{{ old('creation_key', $creationKey) }}">
            <flux:input name="shop_name" label="Nom de la boutique" :value="old('shop_name')" required />
            <flux:input name="slug" label="Nom dans l’adresse, sans espaces" :value="old('slug')" />
            <flux:text>Adresse : slug.{{ $baseDomain }}. Base : boutique_slug. Le nom de base reste stable si la boutique est renommée. Le slug initial est limité techniquement à 55 caractères.</flux:text>
            <flux:input name="settings[business_type]" label="Type d’activité" :value="old('settings.business_type', $defaults['business_type'])" />
            <flux:input name="settings[theme_code]" label="Thème" :value="old('settings.theme_code', $defaults['theme_code'])" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input type="color" name="settings[colors][primary]" label="Couleur principale" :value="old('settings.colors.primary', $defaults['colors']['primary'])" />
                <flux:input type="color" name="settings[colors][secondary]" label="Couleur secondaire" :value="old('settings.colors.secondary', $defaults['colors']['secondary'])" />
            </div>
            <flux:input type="number" name="settings[cart_lifetime_days]" label="Durée du panier en jours" min="1" :value="old('settings.cart_lifetime_days', $defaults['cart_lifetime_days'])" />
            <div class="flex flex-wrap gap-3">
                <flux:button type="submit" variant="primary">Créer la boutique</flux:button>
                <flux:button :href="route('central.tenants.index')">Retour aux boutiques</flux:button>
            </div>
        </form>
        @endif
    </section>
</x-layouts::app>
