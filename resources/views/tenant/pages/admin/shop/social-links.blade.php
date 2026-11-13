<x-tenant::layouts.app :title="__('Réseaux sociaux')" :shop-name="$shop->shop_name" :user-name="$userName">
    <div class="mx-auto max-w-4xl space-y-6">
        <flux:heading size="xl">Liens sociaux publics</flux:heading>
        <nav class="flex gap-4 text-sm underline">
            <a href="{{ route('tenant.admin.shop.profile', absolute: false) }}">Profil</a>
            <a href="{{ route('tenant.admin.shop.addresses.index', absolute: false) }}">Adresses et horaires</a>
        </nav>
        @if (session('status')) <flux:callout>{{ session('status') }}</flux:callout> @endif
        @if ($errors->any())<div role="alert" class="rounded border border-red-500 p-3 text-sm text-red-600"><ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
        <section class="space-y-2">
            @forelse ($items as $item)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded border p-3">
                    <div><strong>{{ $item->payload['network'] ?? '' }}</strong> {{ $item->label }}
                        <p class="text-sm text-zinc-500">{{ $item->payload['url'] ?? '' }} — {{ $item->visible ? 'Visible' : 'Masqué' }}</p>
                    </div>
                    <div class="flex gap-3 text-sm">
                        @can('update', $item)<a class="underline" href="{{ route('tenant.admin.shop.social-links.edit', $item->uuid, false) }}">Modifier</a>@endcan
                        @can('archive', $item)
                            <form method="POST" action="{{ route('tenant.admin.shop.social-links.archive', $item->uuid, false) }}" onsubmit="return confirm('Archiver ce lien ?')">
                                @csrf @method('DELETE')<input type="hidden" name="expected_state" value="{{ $tokens[$item->uuid] }}"><button class="underline">Archiver</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @empty <p class="text-sm text-zinc-500">Aucun lien social.</p> @endforelse
        </section>
        @if ($editing ? auth('tenant')->user()->can('shop.social-links.update') : auth('tenant')->user()->can('shop.social-links.create'))
            <section class="rounded border p-4">
                <flux:heading>{{ $editing ? 'Modifier le lien' : 'Ajouter un lien social' }}</flux:heading>
                <form method="POST" action="{{ $editing ? route('tenant.admin.shop.social-links.update', $editing->uuid, false) : route('tenant.admin.shop.social-links.store', absolute: false) }}" class="mt-4 space-y-4">
                    @csrf @if ($editing) @method('PUT') <input type="hidden" name="expected_state" value="{{ $tokens[$editing->uuid] }}"> @endif
                    <label class="block text-sm">Réseau (Facebook, Instagram, TikTok, etc.) <input name="network" required maxlength="255" value="{{ old('network', $editing?->payload['network'] ?? '') }}" class="mt-1 w-full rounded border p-2 text-zinc-900"></label>
                    <label class="block text-sm">URL publique <input name="url" required type="url" maxlength="255" value="{{ old('url', $editing?->payload['url'] ?? '') }}" class="mt-1 w-full rounded border p-2 text-zinc-900"></label>
                    <label class="block text-sm">Titre facultatif <input name="label" maxlength="255" value="{{ old('label', $editing?->label ?? '') }}" class="mt-1 w-full rounded border p-2 text-zinc-900"></label>
                    <label class="block text-sm">Lieu associé (facultatif)
                        <select name="address_uuid" class="mt-1 w-full rounded border p-2 text-zinc-900">
                            <option value="">Boutique entière</option>
                            @foreach ($addresses as $address)
                                <option value="{{ $address->uuid }}" @selected(old('address_uuid', $editing?->address?->uuid ?? '') === $address->uuid)>{{ $address->label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm">Position <input type="number" name="position" required min="0" value="{{ old('position', $editing?->position ?? 0) }}" class="mt-1 w-full rounded border p-2 text-zinc-900"></label>
                    <label class="block text-sm"><input type="hidden" name="visible" value="0"><input type="checkbox" name="visible" value="1" @checked(old('visible', $editing?->visible ?? true))> Visible</label>
                    <button class="rounded bg-zinc-900 px-5 py-2 text-white dark:bg-white dark:text-zinc-900">{{ $editing ? 'Enregistrer' : 'Créer le lien' }}</button>
                    @if ($editing) <a class="text-sm underline" href="{{ route('tenant.admin.shop.social-links.index', absolute: false) }}">Annuler</a> @endif
                </form>
            </section>
        @endif
    </div>
</x-tenant::layouts.app>
