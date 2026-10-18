<x-layouts::auth title="Configurer votre première boutique">
    <div class="space-y-6">
        <flux:heading size="xl">Configurez votre première boutique</flux:heading>
        <flux:text>Votre adresse e-mail est vérifiée et votre inscription est approuvée. Choisissez maintenant le nom et l’adresse de votre boutique. Elle sera créée uniquement après votre confirmation.</flux:text>
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <form method="POST" action="{{ route('owner.first-shop.store') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="creation_key" value="{{ session('first_shop_creation_key') }}">
            <flux:input name="shop_name" label="Nom de votre boutique" :value="old('shop_name')" required maxlength="255" />
            <flux:input name="slug" label="Adresse de votre boutique" :value="old('slug')" placeholder="ma-boutique" description="Lettres minuscules, chiffres et tirets. Laissez vide pour utiliser le nom simplifié." />
            <flux:text>Votre adresse se terminera par .{{ config('tenancy.saas_base_domain') }}.</flux:text>
            <flux:input name="settings[business_type]" label="Type d’activité" :value="old('settings.business_type', $settings['business_type'])" />
            <flux:input name="settings[theme_code]" label="Thème" :value="old('settings.theme_code', $settings['theme_code'])" />
            <flux:input type="color" name="settings[colors][primary]" label="Couleur principale" :value="old('settings.colors.primary', $settings['colors']['primary'])" />
            <flux:input type="color" name="settings[colors][secondary]" label="Couleur secondaire" :value="old('settings.colors.secondary', $settings['colors']['secondary'])" />
            <flux:input type="number" min="1" name="settings[cart_lifetime_days]" label="Durée du panier en jours" :value="old('settings.cart_lifetime_days', $settings['cart_lifetime_days'])" />
            <flux:button type="submit" variant="primary">Créer ma première boutique</flux:button>
        </form>
        <form method="POST" action="{{ route('logout') }}">@csrf <flux:button type="submit" variant="ghost">Se déconnecter</flux:button></form>
    </div>
</x-layouts::auth>
