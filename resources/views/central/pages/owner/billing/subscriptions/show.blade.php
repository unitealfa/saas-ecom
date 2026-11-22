<x-layouts::app title="Mon abonnement">
    <section class="mx-auto flex w-full max-w-4xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $subscription->plan->name }}</flux:heading>
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        <flux:text>État : {{ $subscription->status->label() }}. Prix convenu : {{ $subscription->agreed_amount }} DZD.</flux:text>
        <flux:text>Période : {{ $subscription->effectiveStartsAt()->format('d/m/Y') }} — {{ $subscription->effectiveEndsAt()?->format('d/m/Y') ?? 'Sans date de fin' }}.</flux:text>
        @if ($subscription->request?->current_subscription_id === $subscription->id)
            <flux:button :href="route('owner.subscription-requests.show', $subscription->request)">Consulter ma demande</flux:button>
            @can('update', $subscription->request)
                <flux:button :href="route('owner.subscription-requests.show', $subscription->request).'#modifier'">Modifier ou annuler ma demande non payée</flux:button>
            @endcan
        @endif
        @can('startNow', $subscription)
            <section id="demarrer" class="flex flex-col gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading>Appliquer ce plan maintenant</flux:heading>
                @include('central.pages.owner.billing.subscriptions.current-plan-warning')
                <flux:heading>Nouveau plan : {{ $subscription->plan->name }}</flux:heading>
                <flux:text>Début : aujourd’hui. Nouvelle période complète : {{ $subscription->period->label() }}. Fin : {{ ($subscription->period === \App\Enums\Central\Subscriptions\PeriodEnum::MONTHLY ? now()->addMonthNoOverflow() : now()->addYearNoOverflow())->format('d/m/Y') }}.</flux:text>
                <ul class="list-inside list-disc text-sm">
                    @foreach ($subscription->plan->features as $grant)
                        @if ($grant->is_active && $grant->feature?->is_active)
                            <li>{{ $grant->feature->name }} : {{ $grant->feature->value_type === \App\Enums\Central\Features\ValueTypeEnum::BOOLEAN ? 'Inclus' : ($grant->limit === null ? 'Sans limite' : $grant->limit.' '.$grant->feature->unit) }}</li>
                        @endif
                    @endforeach
                </ul>
                <flux:text>{{ $subscription->agreed_amount === '0.00' ? 'Cette période à 0,00 DZD a déjà été approuvée.' : 'Cette période est déjà payée. Aucun nouveau paiement ni remboursement.' }}</flux:text>
                <form method="POST" action="{{ route('owner.subscriptions.start-now', $subscription) }}" class="flex flex-col gap-3">
                    @csrf
                    <input type="hidden" name="expected_state" value="{{ $subscription->transitionState() }}">
                    <input type="hidden" name="expected_active_uuid" value="{{ $currentSubscription?->uuid }}">
                    <input type="hidden" name="expected_active_state" value="{{ $currentSubscription?->transitionState() }}">
                    <flux:checkbox name="confirmed" value="1" label="Je confirme l’arrêt des droits actuels et le démarrage immédiat de ce plan." required />
                    <flux:button type="submit" variant="primary">Confirmer et appliquer maintenant</flux:button>
                </form>
            </section>
        @endcan
        @forelse ($installments as $installment)
            <flux:heading>{{ $installment->installment_amount === '0.00' ? 'Période à 0,00 DZD' : 'Règlement intégral : '.$installment->installment_amount.' DZD — '.$installment->installment_status->label() }}</flux:heading>
            @forelse ($installment->invoices as $invoice)
                <flux:button :href="route('owner.invoices.show', $invoice)">Facture {{ $invoice->number ?? 'en préparation' }}</flux:button>
            @empty
                <flux:callout>{{ $installment->installment_amount === '0.00' ? 'La facture sera disponible après validation administrative.' : 'La facture définitive et le reçu sont produits après confirmation intégrale des fonds, avant l’activation des droits.' }}</flux:callout>
            @endforelse
        @empty<flux:text>Aucune échéance payante : l’offre gratuite n’exige pas de paiement.</flux:text>@endforelse
        <flux:button :href="route('owner.subscriptions.index')">Retour aux offres</flux:button>
        <flux:button :href="route('owner.billing.index')">Factures, paiements et reçus</flux:button>
    </section>
</x-layouts::app>
