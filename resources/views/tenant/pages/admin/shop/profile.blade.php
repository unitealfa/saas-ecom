<x-tenant::layouts.app :title="__('Profil de la boutique')" :shop-name="$shop->shop_name" :user-name="$userName">
    <div class="mx-auto max-w-4xl space-y-6">
        <flux:heading size="xl">Profil de la boutique</flux:heading>
        <p class="text-sm text-zinc-500">Nom : <strong>{{ $shop->shop_name }}</strong> — ce nom est administré depuis l'espace central, pas ici.</p>
        <nav class="flex gap-4 text-sm underline">
            <a href="{{ route('tenant.admin.shop.profile', absolute: false) }}">Profil</a>
            @can('viewAny', [\App\Models\Tenant\Shop\ShopAddress::class, $shop])
                <a href="{{ route('tenant.admin.shop.addresses.index', absolute: false) }}">Adresses et horaires</a>
            @endcan
            @can('viewAny', [\App\Models\Tenant\Shop\ShopSocialLink::class, $shop])
                <a href="{{ route('tenant.admin.shop.social-links.index', absolute: false) }}">Réseaux sociaux</a>
            @endcan
        </nav>
        @if (session('status')) <flux:callout>{{ session('status') }}</flux:callout> @endif
        @if ($errors->any())
            <div role="alert" class="rounded border border-red-500 p-3 text-sm text-red-600">
                <strong>Enregistrement refusé :</strong>
                <ul class="list-disc pl-6">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
            </div>
        @endif
        @can('update', $shop)
            <form action="{{ route('tenant.admin.shop.profile.update', absolute: false) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf @method('PUT')
                <input type="hidden" name="expected_state" value="{{ $expectedState }}">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="block text-sm">Type d'activité
                        <input name="business_type" required maxlength="255" value="{{ old('business_type', $shop->business_type) }}" class="mt-1 w-full rounded border p-2 text-zinc-900">
                    </label>
                    <label class="block text-sm">Langue
                        <input name="locale" required maxlength="10" value="{{ old('locale', $shop->locale) }}" class="mt-1 w-full rounded border p-2 text-zinc-900">
                    </label>
                    <label class="block text-sm">Courriel public
                        <input name="contact_email" type="email" maxlength="255" value="{{ old('contact_email', $shop->contact_email) }}" class="mt-1 w-full rounded border p-2 text-zinc-900">
                    </label>
                    <label class="block text-sm">Téléphone public
                        <input name="contact_phone" maxlength="255" value="{{ old('contact_phone', $shop->contact_phone) }}" class="mt-1 w-full rounded border p-2 text-zinc-900">
                    </label>
                    <label class="block text-sm">WhatsApp public
                        <input name="contact_whatsapp" maxlength="255" value="{{ old('contact_whatsapp', $shop->contact_whatsapp) }}" class="mt-1 w-full rounded border p-2 text-zinc-900">
                    </label>
                    <label class="block text-sm">Fuseau horaire
                        <input name="timezone" required value="{{ old('timezone', $shop->timezone) }}" class="mt-1 w-full rounded border p-2 text-zinc-900">
                    </label>
                </div>
                <label class="block text-sm">Description courte
                    <textarea name="description" rows="2" maxlength="2000" class="mt-1 w-full rounded border p-2 text-zinc-900">{{ old('description', $shop->description) }}</textarea>
                </label>
                <label class="block text-sm">À propos
                    <textarea name="about" rows="5" maxlength="10000" class="mt-1 w-full rounded border p-2 text-zinc-900">{{ old('about', $shop->about) }}</textarea>
                </label>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="block text-sm">Code du thème (existant : default)
                        <input name="theme_code" required maxlength="255" value="{{ old('theme_code', $shop->theme_code) }}" class="mt-1 w-full rounded border p-2 text-zinc-900">
                    </label>
                    <label class="block text-sm">Durée du panier invité (jours)
                        <input type="number" name="cart_lifetime_days" required min="1" value="{{ old('cart_lifetime_days', $shop->cart_lifetime_days) }}" class="mt-1 w-full rounded border p-2 text-zinc-900">
                    </label>
                </div>
                <fieldset class="rounded border p-4">
                    <legend class="px-2 text-sm font-semibold">Couleurs (format #RRGGBB)</legend>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach (($shop->colors ?: ['primary' => '#2563EB', 'secondary' => '#FFFFFF']) as $name => $color)
                            <label class="block text-sm">{{ $name }}
                                <input name="colors[{{ $name }}]" maxlength="9" required value="{{ old('colors.'.$name, $color) }}" class="mt-1 w-full rounded border p-2 text-zinc-900">
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                @can('branding', $shop)
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="block text-sm">Logo PNG/JPEG/WebP (max. 2 Mo)
                            @if ($shop->logoMedia)<img class="my-2 h-20 max-w-full object-contain" alt="Logo actuel" src="{{ route('tenant.shop-profile.branding', ['kind'=>'logo','v'=>$shop->logoMedia->uuid], false) }}">@endif
                            <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="mt-1 block w-full">
                        </label>
                        <label class="block text-sm">Favicon PNG/JPEG/WebP (max. 1 Mo)
                            @if ($shop->faviconMedia)<img class="my-2 h-12 w-12 object-contain" alt="Favicon actuel" src="{{ route('tenant.shop-profile.branding', ['kind'=>'favicon','v'=>$shop->faviconMedia->uuid], false) }}">@endif
                            <input type="file" name="favicon" accept="image/png,image/jpeg,image/webp" class="mt-1 block w-full">
                        </label>
                    </div>
                @endcan
                <button class="rounded bg-zinc-900 px-5 py-2 text-white dark:bg-white dark:text-zinc-900">Enregistrer le profil</button>
            </form>
        @else
            <p class="text-sm">Votre rôle autorise la consultation, mais pas la modification de ce profil.</p>
        @endcan
    </div>
</x-tenant::layouts.app>
