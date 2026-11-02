<div class="space-y-2" x-data="{
    endpoint: @js(route('geography.lookup.index')), kind: {{ $kind }},
    rows: [], search: '', page: 1, lastPage: 1, loading: false, failed: false,
    init() {
        if (this.kind === 1 || this.province) this.load(1);
        if (this.kind === 2) this.$watch('province', () => { this.municipality = ''; this.rows = []; this.load(1); });
    },
    async load(page) {
        if (this.loading || (this.kind === 2 && !this.province)) return;
        const parent = this.province;
        this.loading = true; this.failed = false;
        try {
            const url = new URL(this.endpoint);
            url.searchParams.set('type', this.kind);
            url.searchParams.set('search', this.search);
            url.searchParams.set('page', page);
            if (this.kind === 2) url.searchParams.set('parent_uuid', parent);
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error('Geography lookup failed');
            const data = await response.json();
            if (this.kind === 1 || parent === this.province) {
                this.rows = data.data; this.page = data.meta.page; this.lastPage = data.meta.last_page;
            }
        } catch { this.failed = true; }
        finally {
            this.loading = false;
            if (this.kind === 2 && parent !== this.province) this.load(1);
        }
    }
}">
    <flux:input label="Rechercher : {{ $label }}" x-model="search" x-on:keydown.enter.prevent="load(1)" />
    <flux:button type="button" x-on:click="load(1)" x-bind:disabled="loading">Rechercher</flux:button>
    <label class="block">
        {{ $label }}
        <select name="{{ $field }}" x-model="{{ $kind === 1 ? 'province' : 'municipality' }}" @required($required)
            class="w-full rounded-lg border border-zinc-300 bg-white p-2 text-zinc-900 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white">
            <option value="">{{ $required ? 'Sélectionner une wilaya' : 'Non précisée' }}</option>
            <template x-if="{{ $kind === 1 ? 'province' : 'municipality' }} && !rows.some(row => row.uuid === {{ $kind === 1 ? 'province' : 'municipality' }})">
                <option :value="{{ $kind === 1 ? 'province' : 'municipality' }}">Sélection précédente</option>
            </template>
            <template x-for="row in rows" :key="row.uuid"><option :value="row.uuid" x-text="row.name_fr + ' — ' + row.code"></option></template>
        </select>
    </label>
    <div class="flex gap-2">
        <flux:button type="button" x-on:click="load(page - 1)" x-bind:disabled="loading || page <= 1">Précédent</flux:button>
        <flux:button type="button" x-on:click="load(page + 1)" x-bind:disabled="loading || page >= lastPage">Suivant</flux:button>
    </div>
    <p x-cloak x-show="failed" role="alert">La recherche est indisponible. Réessayez.</p>
</div>
