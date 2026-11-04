<x-layouts::app title="Compteurs de facturation">
    <section class="mx-auto flex w-full max-w-5xl flex-col gap-6">
        <flux:heading size="xl" level="1">Facturation — Compteurs et numérotations</flux:heading>
        <flux:text>Un compteur par type de document et exercice. Les numéros sont réservés par le traitement d’émission, jamais modifiés depuis cette page.</flux:text>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        @if ($initializableTypes !== [])
            <form method="POST" action="{{ route('central.billing-sequences.initialize') }}" class="flex flex-wrap items-end gap-3"
                x-data="{ submitting: false }" x-on:submit="submitting = true">
                @csrf
                <flux:select name="document_type" label="Série à initialiser" required>
                    @foreach ($initializableTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach
                </flux:select>
                <flux:input type="number" name="fiscal_year" label="Exercice" min="1000" max="9999" :value="now()->year" required />
                <flux:button type="submit" x-bind:disabled="submitting" x-bind:aria-busy="submitting">Initialiser le compteur</flux:button>
                <flux:text x-cloak x-show="submitting" role="status">Initialisation en cours…</flux:text>
            </form>
            <flux:text>L’initialisation exige le droit d’émission du document. Elle retrouve un compteur existant et ne réserve aucun numéro.</flux:text>
        @endif
        @can('viewAny', \App\Models\Central\Billing\BillingRule::class)<flux:button :href="route('central.billing-rules.index')">Configuration des règles</flux:button>@endcan
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3">Document</th><th class="p-3">Exercice</th><th class="p-3">Préfixe</th><th class="p-3">Prochain numéro</th></tr></thead>
                <tbody>
                    @forelse ($sequences as $sequence)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td class="p-3">{{ $sequence->document_type->label() }}</td><td class="p-3">{{ $sequence->fiscal_year }}</td>
                            <td class="p-3">{{ $sequence->prefix }}</td><td class="p-3">{{ $sequence->next_number }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-3">Aucun compteur créé. Ils seront initialisés par le workflow d’émission des documents.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $sequences->links() }}
    </section>
</x-layouts::app>
