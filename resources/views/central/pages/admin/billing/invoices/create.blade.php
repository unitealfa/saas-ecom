@php
    $oldText = static fn (string $key, mixed $default = null): string => is_scalar($value = old($key, $default)) ? (string) $value : '';
@endphp
<x-layouts::app title="Préparer une facture">
    <section class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Préparer l’échéance et sa facture</flux:heading>
        <flux:text>{{ $subscription->owner?->name }} — {{ $subscription->plan?->name }} — {{ $subscription->agreed_amount }} DZD</flux:text>
        <flux:text>Le montant et la période viennent de l’abonnement. Cette préparation sans taxe doit être justifiée. Elle ne constitue ni une émission définitive ni un paiement.</flux:text>
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <form method="POST" action="{{ route('central.invoices.store', $subscription) }}" class="flex flex-col gap-5">
            @csrf
            <input type="hidden" name="expected_state" value="{{ $subscription->transitionState() }}">
            <flux:select name="billing_rule_uuid" label="Règle actuellement applicable" required>
                <option value="">Choisir une règle</option>
                @foreach ($rules as $rule)
                    <option value="{{ $rule->uuid }}" @selected($oldText('billing_rule_uuid') === $rule->uuid)>{{ $rule->code }} — version {{ $rule->version }}</option>
                @endforeach
            </flux:select>
            <flux:input name="issuer_name" label="Nom de l’émetteur" :value="$oldText('issuer_name', 'Aydra')" required />
            <flux:textarea name="no_tax_reason" label="Motif de la préparation sans taxe" required placeholder="Ex. : scénario de démonstration sans taxe expressément autorisé">{{ $oldText('no_tax_reason') }}</flux:textarea>
            <flux:text>Rejouer cette préparation pour la même période retrouve la même échéance et la même facture.</flux:text>
            <flux:button type="submit" variant="primary">Préparer l’échéance et la facture</flux:button>
        </form>
    </section>
</x-layouts::app>
