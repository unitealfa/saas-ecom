<x-layouts::app :title="__('Créer une boutique')">
    <section class="mx-auto flex max-w-3xl flex-col gap-6">
        <flux:heading size="xl" level="1">Boutiques de {{ $user->name }}</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        <flux:text>Chaque boutique possède sa propre base MySQL. L’offre gratuite limite ce propriétaire à deux boutiques ; une préparation en cours ou en échec réserve aussi une place.</flux:text>
        @foreach ($shops as $shop)
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading>{{ $shop->shop_name }}</flux:heading>
                <flux:text>{{ $shop->domains->pluck('domain')->join(', ') }}</flux:text>
                <flux:text>{{ $shop->schema_version ? 'Base préparée ; accès commercial en attente de validation.' : 'Préparation de la base en attente ou en cours.' }}</flux:text>
            </div>
        @endforeach
        @if ($errors->any())
            <flux:callout variant="danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </flux:callout>
        @endif
        <form method="POST" action="{{ route('central.users.tenants.store', $user) }}" class="flex flex-col gap-5">
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
                <flux:button :href="route('central.users.index')">Retour aux utilisateurs</flux:button>
            </div>
        </form>
    </section>
</x-layouts::app>
