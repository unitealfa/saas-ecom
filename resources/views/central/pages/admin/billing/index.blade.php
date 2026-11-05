<x-layouts::app title="Configuration de facturation">
    <section class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <flux:heading size="xl" level="1">Facturation — Configuration</flux:heading>
        <flux:text>Règles versionnées de facturation du SaaS. Ce module configure les règles ; il ne génère pas encore les factures.</flux:text>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <div class="flex flex-wrap gap-3">
            @can('create', \App\Models\Central\Billing\BillingRule::class)
                <flux:button variant="primary" :href="route('central.billing-rules.create')">Créer une règle</flux:button>
            @endcan
            @can('viewAny', \App\Models\Central\Billing\BillingSequence::class)
                <flux:button :href="route('central.billing-sequences.index')">Compteurs et numérotations</flux:button>
            @endcan
        </div>
        <form method="GET" class="flex flex-wrap items-end gap-3" x-data="{ submitting: false }" x-on:submit="submitting = true">
            <flux:input name="search" label="Code de la règle" :value="request('search')" />
            <flux:select name="policy_status" label="État">
                <option value="">Tous</option>
                @foreach (\App\Enums\Central\Billing\PolicyStatusEnum::cases() as $status)
                    <option value="{{ $status->value }}" @selected((string) request('policy_status') === (string) $status->value)>{{ $status->label() }}</option>
                @endforeach
            </flux:select>
            <flux:input type="number" name="version" label="Version" min="1" :value="request('version')" />
            <flux:input type="datetime-local" name="period_start" label="Début de période inclus" :value="request('period_start')" />
            <flux:input type="datetime-local" name="period_end" label="Fin de période exclue" :value="request('period_end')" />
            <flux:button type="submit" x-bind:disabled="submitting" x-bind:aria-busy="submitting">Rechercher</flux:button>
            <flux:text x-cloak x-show="submitting" role="status">Recherche en cours…</flux:text>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3">Code</th><th class="p-3">Version</th><th class="p-3">État</th><th class="p-3">Période d’effet</th><th class="p-3">Actions</th></tr></thead>
                <tbody>
                    @forelse ($rules as $rule)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td class="p-3">{{ $rule->code }}</td><td class="p-3">{{ $rule->version }}</td>
                            <td class="p-3">{{ $rule->policy_status->label() }}</td>
                            <td class="p-3">{{ $rule->effective_at?->format('d/m/Y H:i') ?? 'Non définie' }} — {{ $rule->ends_at?->format('d/m/Y H:i') ?? 'Sans fin prévue' }}</td>
                            <td class="p-3">
                                <div class="flex flex-wrap gap-2">
                                    <flux:button size="sm" :href="route('central.billing-rules.show', $rule)">Informations</flux:button>
                                    @can('update', $rule)<flux:button size="sm" :href="route('central.billing-rules.edit', $rule)">Modifier</flux:button>@endcan
                                    @can('createVersion', $rule)<flux:button size="sm" :href="route('central.billing-rules.versions.create', $rule)">Nouvelle version</flux:button>@endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-3">Aucune règle de facturation.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $rules->links() }}
    </section>
</x-layouts::app>
