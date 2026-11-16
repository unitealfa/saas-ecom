<x-layouts::app title="Mon abonnement">
    <section class="mx-auto flex w-full max-w-4xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $subscription->plan->name }}</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        <flux:text>État : {{ $subscription->status->label() }}. Prix convenu : {{ $subscription->agreed_amount }} DZD.</flux:text>
        <flux:text>Période : {{ $subscription->period_starts_at->format('d/m/Y') }} — {{ $subscription->period_ends_at?->format('d/m/Y') ?? 'Sans date de fin' }}.</flux:text>
        @forelse ($installments as $installment)
            <flux:heading>Règlement intégral : {{ $installment->installment_amount }} DZD — {{ $installment->installment_status->label() }}</flux:heading>
            @forelse ($installment->invoices as $invoice)
                <flux:button :href="route('owner.invoices.show', $invoice)">Facture {{ $invoice->number ?? 'en préparation' }}</flux:button>
            @empty
                <flux:callout>La préparation interne précède le paiement. La facture définitive et le reçu sont produits après confirmation intégrale des fonds, avant l’activation des droits.</flux:callout>
            @endforelse
        @empty<flux:text>Aucune échéance payante : l’offre gratuite n’exige pas de paiement.</flux:text>@endforelse
        <flux:button :href="route('owner.subscriptions.index')">Retour aux offres</flux:button>
        <flux:button :href="route('owner.billing.index')">Factures, paiements et reçus</flux:button>
    </section>
</x-layouts::app>
