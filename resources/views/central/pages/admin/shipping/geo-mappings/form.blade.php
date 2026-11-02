@foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
<form method="POST" action="{{ $mapping === null ? route('central.carrier-mappings.store') : route('central.carrier-mappings.update', $mapping) }}" class="flex flex-col gap-5">
    @csrf
    @if ($mapping !== null)
        @method('PUT')
        <input type="hidden" name="expected_state" value="{{ $mapping->managementState() }}">
        <input type="hidden" name="carrier_uuid" value="{{ $mapping->carrier->uuid }}">
        <input type="hidden" name="geographic_area_uuid" value="{{ $mapping->area->uuid }}">
        <flux:text>Transporteur : {{ $mapping->carrier->name }} — Zone : {{ $mapping->area->name_fr }} ({{ $mapping->zone_type->label() }}). Ces liens sont permanents.</flux:text>
    @else
        <flux:select name="carrier_uuid" label="Transporteur" required>
            <option value="">Sélectionner un transporteur</option>
            @foreach ($carriers as $carrier)<option value="{{ $carrier->uuid }}" @selected(old('carrier_uuid', request('carrier_uuid')) === $carrier->uuid)>{{ $carrier->name }}</option>@endforeach
        </flux:select>
        <div class="space-y-3" x-data="{
            endpoint: @js(route('geography.lookup.index')),
            search: '', type: '1', rows: [], page: 1, lastPage: 1,
            loading: false, failed: false, selected: @js(old('geographic_area_uuid', '')),
            init() { this.load(1); },
            async load(page) {
                if (this.loading) return;
                this.loading = true; this.failed = false;
                try {
                    const url = new URL(this.endpoint);
                    url.searchParams.set('type', this.type);
                    url.searchParams.set('search', this.search);
                    url.searchParams.set('page', page);
                    const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    if (!response.ok) throw new Error('Geography lookup failed');
                    const data = await response.json();
                    this.rows = data.data;
                    this.page = data.meta.page; this.lastPage = data.meta.last_page;
                } catch { this.failed = true; }
                finally { this.loading = false; }
            }
        }">
            <flux:select label="Type de zone" x-model="type" x-on:change="load(1)">
                @foreach (\App\Enums\Central\Geography\GeoZoneTypeEnum::cases() as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach
            </flux:select>
            <flux:input label="Chercher une zone" x-model="search" x-on:keydown.enter.prevent="load(1)" placeholder="Nom ou code Aydra" />
            <flux:button type="button" x-on:click="load(1)" x-bind:disabled="loading">Rechercher la zone</flux:button>
            <select name="geographic_area_uuid" x-model="selected" required aria-label="Zone géographique" class="w-full rounded-lg border border-zinc-300 bg-white p-2 text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
                <option value="">Sélectionner une zone</option>
                @if (old('geographic_area_uuid'))<option value="{{ old('geographic_area_uuid') }}">Zone précédemment sélectionnée</option>@endif
                <template x-for="row in rows" :key="row.uuid"><option :value="row.uuid" x-text="row.name_fr + ' — ' + row.code"></option></template>
            </select>
            <div class="flex gap-2">
                <flux:button type="button" x-on:click="load(page - 1)" x-bind:disabled="loading || page <= 1">Précédent</flux:button>
                <flux:button type="button" x-on:click="load(page + 1)" x-bind:disabled="loading || page >= lastPage">Suivant</flux:button>
            </div>
            <p x-cloak x-show="failed" role="alert">La recherche est indisponible. Réessayez.</p>
            <flux:text>Seules les zones disponibles sont proposées. Leur code Aydra n’est pas nécessairement le code attendu par le transporteur.</flux:text>
        </div>
    @endif
    <flux:input name="external_code" label="Code de la zone chez le transporteur" :value="old('external_code', $mapping?->external_code)" required />
    <flux:input name="external_name" label="Nom de la zone chez le transporteur" :value="old('external_name', $mapping?->external_name)" required />
    <flux:input name="external_province_code" label="Code de la wilaya chez le transporteur" :value="old('external_province_code', $mapping?->external_province_code)" required />
    <flux:input name="verification_source" label="Source vérifiée" :value="old('verification_source', $mapping?->verification_source)" placeholder="Document ou export du transporteur utilisé pour contrôler les codes" required />
    <flux:text>En enregistrant, vous confirmez avoir contrôlé ces codes dans la source renseignée. Utilisez uniquement les codes communs du réseau, jamais les exceptions privées d’un compte. Ils seront publiés comme correspondance vérifiée ; aucun contrôle API automatique n’est effectué.</flux:text>
    <div class="flex gap-3">
        <flux:button type="submit" variant="primary">Enregistrer</flux:button>
        @can('viewAny', \App\Models\Central\Shipping\CarrierGeoMapping::class)<flux:button :href="route('central.carrier-mappings.index')">Retour aux correspondances</flux:button>@endcan
    </div>
</form>
