<x-layouts::app :title="$ownerMode ? 'Mes demandes d’abonnement' : 'Demandes d’abonnement'">
    <section class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $ownerMode ? 'Mes demandes d’abonnement' : 'Abonnements — Demandes' }}</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        <nav aria-label="Filtrer les demandes" class="flex flex-wrap gap-2">
            @foreach (\App\Enums\Central\Subscriptions\RequestFilterEnum::cases() as $filter)
                <flux:button :href="route($ownerMode ? 'owner.subscription-requests.index' : 'central.subscription-requests.index', ['filter' => $filter->value, 'search' => request('search')])"
                    :variant="request('filter', 'all') === $filter->value ? 'primary' : 'outline'">{{ $filter->label() }}</flux:button>
            @endforeach
        </nav>
        <form method="GET" class="flex flex-wrap items-end gap-3">
            @if (request('filter'))<input type="hidden" name="filter" value="{{ request('filter') }}">@endif
            <flux:input name="search" label="Référence, nom du commerçant, e-mail ou facture" :value="request('search')" />
            <flux:select name="status" label="État">
                <option value="">Tous les états</option>
                @foreach (\App\Enums\Central\Subscriptions\RequestStatusEnum::cases() as $state)
                    @continue($state === \App\Enums\Central\Subscriptions\RequestStatusEnum::INVOICE_PENDING)
                    <option value="{{ $state->value }}" @selected((string) request('status') === (string) $state->value)>{{ $state->label() }}</option>
                @endforeach
            </flux:select>
            <flux:button type="submit">Rechercher</flux:button>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3">Référence</th><th class="p-3">Commerçant</th><th class="p-3">Offre</th><th class="p-3">Montant TTC</th><th class="p-3">Méthode de paiement</th><th class="p-3">Date de demande</th><th class="p-3">Statut</th><th class="p-3">Actions</th></tr></thead>
                <tbody>
                    @forelse ($requests as $item)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td class="break-all p-3 text-xs">{{ $item->uuid }}</td>
                            <td class="p-3">{{ $item->owner?->name }}</td><td class="p-3">{{ $item->subscription?->plan->name }}</td>
                            <td class="p-3">{{ $item->subscription?->agreed_amount }} DZD</td><td class="p-3">{{ $item->payment_choice->label() }}</td>
                            <td class="p-3">{{ $item->submitted_at?->format('d/m/Y H:i') }}</td><td class="p-3">{{ $item->status->label() }}</td>
                            <td class="p-3"><flux:button :href="route($ownerMode ? 'owner.subscription-requests.show' : 'central.subscription-requests.show', $item)">Informations</flux:button></td>
                        </tr>
                    @empty<tr><td colspan="8" class="p-3">Aucune demande.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        {{ $requests->links() }}
        @if ($ownerMode)<flux:button :href="route('owner.subscriptions.index')">Choisir une offre</flux:button>@endif
    </section>
</x-layouts::app>
