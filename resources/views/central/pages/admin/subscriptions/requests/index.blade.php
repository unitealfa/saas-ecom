<x-layouts::app :title="$ownerMode ? 'Mes demandes d’abonnement' : 'Demandes d’abonnement'">
    <section class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $ownerMode ? 'Mes demandes d’abonnement' : 'Abonnements — Demandes' }}</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <flux:input name="search" label="Facture, e-mail ou UUID de demande" :value="request('search')" />
            <flux:select name="status" label="État">
                <option value="">Tous les états</option>
                @foreach (\App\Enums\Central\Subscriptions\RequestStatusEnum::cases() as $state)
                    <option value="{{ $state->value }}" @selected((string) request('status') === (string) $state->value)>{{ $state->label() }}</option>
                @endforeach
            </flux:select>
            <flux:button type="submit">Rechercher</flux:button>
        </form>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3">Commerçant</th><th class="p-3">Offre</th><th class="p-3">Montant TTC</th><th class="p-3">Paiement</th><th class="p-3">État</th><th class="p-3">Consulter</th></tr></thead>
                <tbody>
                    @forelse ($requests as $item)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td class="p-3">{{ $item->owner?->name }}</td><td class="p-3">{{ $item->subscription?->plan->name }}</td>
                            <td class="p-3">{{ $item->subscription?->agreed_amount }} DZD</td><td class="p-3">{{ $item->payment_choice->label() }}</td>
                            <td class="p-3">{{ $item->status->label() }}</td>
                            <td class="p-3"><flux:button :href="route($ownerMode ? 'owner.subscription-requests.show' : 'central.subscription-requests.show', $item)">Informations</flux:button></td>
                        </tr>
                    @empty<tr><td colspan="6" class="p-3">Aucune demande.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        {{ $requests->links() }}
        @if ($ownerMode)<flux:button :href="route('owner.subscriptions.index')">Choisir une offre</flux:button>@endif
    </section>
</x-layouts::app>
