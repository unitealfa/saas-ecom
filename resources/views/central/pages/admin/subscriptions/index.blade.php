<x-layouts::app :title="__('Abonnements')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Abonnements</flux:heading>
        <flux:text>Recherchez les abonnements par propriétaire, offre ou identifiant.</flux:text>
        @if (session('status'))<flux:text role="status">{{ session('status') }}</flux:text>@endif
        @can('assign', \App\Models\Central\Subscriptions\Subscription::class)
            <div><flux:button :href="route('central.subscriptions.create')" variant="primary">Attribuer un abonnement</flux:button></div>
        @endcan
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <form method="GET" action="{{ route('central.subscriptions.index') }}" class="flex flex-wrap items-end gap-3">
            <flux:input name="search" label="Rechercher" :value="request('search')" placeholder="Nom, e-mail, offre ou UUID" />
            <flux:select name="status" label="État">
                <option value="">Tous</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected((string) request('status') === (string) $status->value)>{{ $status->label() }}</option>
                @endforeach
            </flux:select>
            <flux:button type="submit">Rechercher</flux:button>
        </form>
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
            <table class="w-full text-left text-sm">
                <thead><tr>@foreach (['Propriétaire', 'Offre', 'État', 'Période', 'Montant convenu', 'Actions'] as $heading)<th class="p-4">{{ $heading }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($subscriptions as $subscription)
                        <tr>
                            <td class="p-4">{{ $subscription->owner?->name ?? 'Compte indisponible' }}<div>{{ $subscription->owner?->email }}</div></td>
                            <td class="p-4">{{ $subscription->plan?->name ?? 'Offre indisponible' }}@if ($subscription->plan) — v{{ $subscription->plan->version }}@endif</td>
                            <td class="p-4">{{ $subscription->status?->label() ?? 'Non renseigné' }}</td>
                            <td class="p-4">{{ $subscription->period_starts_at?->format('d/m/Y') }} — {{ $subscription->period_ends_at?->format('d/m/Y') ?? 'Sans échéance' }}</td>
                            <td class="p-4">{{ $subscription->agreed_amount !== null ? $subscription->agreed_amount.' DZD' : 'Non renseigné' }}</td>
                            <td class="p-4"><flux:button :href="route('central.subscriptions.show', $subscription)">Détails</flux:button></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4">Aucun abonnement trouvé.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $subscriptions->links() }}
    </section>
</x-layouts::app>
