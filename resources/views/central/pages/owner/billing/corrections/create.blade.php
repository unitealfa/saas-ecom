<x-layouts::app title="Signaler une erreur sur un paiement">
    <section class="mx-auto flex w-full max-w-3xl flex-col gap-5">
        <flux:heading size="xl" level="1">Signaler une erreur sur un paiement</flux:heading>
        <flux:callout>
            Il s'agit d'une demande d'examen, pas d'une modification du paiement.
            Un montant confirmé, un reçu et une facture ne sont jamais réécrits.
            Aucun remboursement ni paiement partiel n'est déclenché ici.
        </flux:callout>
        <dl class="grid gap-3 sm:grid-cols-2">
            <div><dt>Paiement</dt><dd>PAY-{{ $payment->uuid }}</dd></div>
            <div><dt>Facture</dt><dd>{{ $payment->invoice->number ?? 'En préparation' }}</dd></div>
            <div><dt>Montant historique</dt><dd>{{ $payment->getAttribute('amount') }} {{ $payment->getAttribute('currency') }}</dd></div>
            <div><dt>État actuel</dt><dd>{{ $payment->statusLabel() }}</dd></div>
        </dl>
        @foreach ($errors->all() as $error)
            <flux:text role="alert">{{ $error }}</flux:text>
        @endforeach
        <form method="POST" action="{{ route('owner.billing.corrections.store', $payment) }}" class="flex flex-col gap-4">
            @csrf
            <input type="hidden" name="request_id" value="{{ old('request_id', (string) \Illuminate\Support\Str::uuid()) }}">
            <flux:textarea name="reason" label="Expliquez l'erreur à vérifier (10 à 2 000 caractères)" rows="5" required maxlength="2000">{{ old('reason') }}</flux:textarea>
            <flux:button variant="primary" type="submit">Envoyer mon signalement</flux:button>
        </form>
        <flux:button :href="route('owner.payments.show', $payment)">Retour au paiement</flux:button>
        <flux:button :href="route('owner.billing.index')">Retour à ma facturation</flux:button>
    </section>
</x-layouts::app>
