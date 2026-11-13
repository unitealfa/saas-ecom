<x-tenant::layouts.app :title="__('Adresses et horaires')" :shop-name="$shop->shop_name" :user-name="$userName">
    <div class="mx-auto max-w-5xl space-y-6">
        <flux:heading size="xl">Adresses publiques et horaires</flux:heading>
        <nav class="flex gap-4 text-sm underline">
            <a href="{{ route('tenant.admin.shop.profile', absolute: false) }}">Profil</a>
            <a href="{{ route('tenant.admin.shop.social-links.index', absolute: false) }}">Réseaux sociaux</a>
        </nav>
        @if (session('status')) <flux:callout>{{ session('status') }}</flux:callout> @endif
        @if ($errors->any())<div role="alert" class="rounded border border-red-500 p-3 text-sm text-red-600"><ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
        <section class="space-y-2">
            <flux:heading>Adresses enregistrées</flux:heading>
            @forelse ($items as $item)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded border p-3">
                    <div><strong>{{ $item->label }}</strong> @if ($item->is_primary) <span>(Principale)</span> @endif
                        <p class="text-sm text-zinc-500">{{ $item->payload['address'] ?? '' }} — {{ $item->visible ? 'Visible' : 'Masquée' }}</p>
                    </div>
                    <div class="flex gap-3 text-sm">
                        @can('update', $item)<a class="underline" href="{{ route('tenant.admin.shop.addresses.edit', $item->uuid, false) }}">Modifier</a>@endcan
                        @can('archive', $item)
                            <form method="POST" action="{{ route('tenant.admin.shop.addresses.archive', $item->uuid, false) }}" onsubmit="return confirm('Archiver cette adresse ?')">
                                @csrf @method('DELETE')<input type="hidden" name="expected_state" value="{{ $tokens[$item->uuid] }}">
                                <button class="underline">Archiver</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @empty
                <p class="text-sm text-zinc-500">Aucune adresse enregistrée.</p>
            @endforelse
        </section>
        @if ($editing ? auth('tenant')->user()->can('shop.addresses.update') : auth('tenant')->user()->can('shop.addresses.create'))
            <section class="space-y-3 rounded border p-4">
                <flux:heading>{{ $editing ? 'Modifier l’adresse' : 'Ajouter une adresse' }}</flux:heading>
                <form method="POST" action="{{ $editing ? route('tenant.admin.shop.addresses.update', $editing->uuid, false) : route('tenant.admin.shop.addresses.store', absolute: false) }}" class="space-y-4">
                    @csrf
                    @if ($editing) @method('PUT') <input type="hidden" name="expected_state" value="{{ $tokens[$editing->uuid] }}"> @endif
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="text-sm">Nom du lieu <input required name="label" maxlength="255" value="{{ old('label', $editing?->label) }}" class="mt-1 w-full rounded border p-2 text-zinc-900"></label>
                        <label class="text-sm">Téléphone du lieu <input name="phone" maxlength="255" value="{{ old('phone', $editing?->payload['phone'] ?? '') }}" class="mt-1 w-full rounded border p-2 text-zinc-900"></label>
                        <label class="text-sm">Wilaya officielle
                            <select required name="province_uuid" class="mt-1 w-full rounded border p-2 text-zinc-900">
                                <option value="">Choisir</option>
                                @foreach ($provinces as $province)
                                    <option value="{{ $province['uuid'] }}" @selected(old('province_uuid', $editing?->province_uuid ?? '') === $province['uuid'])>{{ $province['name_fr'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm">Commune officielle
                            <select required name="municipality_uuid" class="mt-1 w-full rounded border p-2 text-zinc-900">
                                <option value="">Choisir</option>
                                @foreach ($provinces as $province)
                                    <optgroup label="{{ $province['name_fr'] }}">
                                        @foreach (($municipalitiesByProvince[$province['uuid']] ?? []) as $municipality)
                                                <option value="{{ $municipality['uuid'] }}" @selected(old('municipality_uuid', $editing?->municipality_uuid ?? '') === $municipality['uuid'])>{{ $municipality['name_fr'] }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </label>
                        <label class="text-sm">Code postal <input name="postal_code" maxlength="255" value="{{ old('postal_code', $editing?->payload['postal_code'] ?? '') }}" class="mt-1 w-full rounded border p-2 text-zinc-900"></label>
                        <label class="text-sm">Position d'affichage <input required type="number" min="0" name="position" value="{{ old('position', $editing?->position ?? 0) }}" class="mt-1 w-full rounded border p-2 text-zinc-900"></label>
                        <label class="text-sm">Latitude (facultatif) <input name="latitude" value="{{ old('latitude', $editing?->payload['latitude'] ?? '') }}" class="mt-1 w-full rounded border p-2 text-zinc-900"></label>
                        <label class="text-sm">Longitude (facultatif) <input name="longitude" value="{{ old('longitude', $editing?->payload['longitude'] ?? '') }}" class="mt-1 w-full rounded border p-2 text-zinc-900"></label>
                    </div>
                    <label class="block text-sm">Adresse publique <textarea required rows="3" name="address" class="mt-1 w-full rounded border p-2 text-zinc-900">{{ old('address', $editing?->payload['address'] ?? '') }}</textarea></label>
                    <label class="block text-sm">Lien de carte (HTTP/HTTPS) <input type="url" name="map_url" maxlength="255" value="{{ old('map_url', $editing?->payload['map_url'] ?? '') }}" class="mt-1 w-full rounded border p-2 text-zinc-900"></label>
                    <label class="block text-sm">Horaires JSON (mon, tue, wed, thu, fri, sat, sun ; fermeture le lendemain possible)
                        <textarea name="opening_hours" rows="5" spellcheck="false" class="mt-1 w-full rounded border p-2 font-mono text-sm text-zinc-900">{{ old('opening_hours', $editing && $editing->payload['opening_hours'] ? json_encode($editing->payload['opening_hours'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '') }}</textarea>
                        <small>Exemple : {"mon":[{"opens_at":"09:00","closes_at":"01:00","closes_next_day":true}]}. Vide = horaires non renseignés ; [] pour un jour = fermé.</small>
                    </label>
                    <div class="flex gap-5 text-sm">
                        <label><input type="hidden" name="visible" value="0"><input type="checkbox" name="visible" value="1" @checked(old('visible', $editing?->visible ?? true))> Visible</label>
                        <label><input type="hidden" name="is_primary" value="0"><input type="checkbox" name="is_primary" value="1" @checked(old('is_primary', $editing?->is_primary ?? false))> Adresse principale</label>
                    </div>
                    <button class="rounded bg-zinc-900 px-5 py-2 text-white dark:bg-white dark:text-zinc-900">{{ $editing ? 'Enregistrer les modifications' : 'Créer l’adresse' }}</button>
                    @if ($editing) <a class="text-sm underline" href="{{ route('tenant.admin.shop.addresses.index', absolute: false) }}">Annuler</a> @endif
                </form>
            </section>
        @endif
    </div>
</x-tenant::layouts.app>
