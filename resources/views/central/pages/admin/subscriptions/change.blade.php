@php($oldText = static fn (string $key, mixed $default = null): string => is_scalar($value = old($key, $default)) ? (string) $value : '')
<x-layouts::app :title="__('Changer l’offre')">
    <section class="mx-auto flex max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Changer l’offre</flux:heading>
        <flux:text>Le tarif intégral est dû. Les anciens droits prennent fin après confirmation pour un remplacement immédiat, ou à leur échéance pour un changement différé.</flux:text>
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <form method="POST" enctype="multipart/form-data" action="{{ route('central.subscriptions.change', $subscription) }}" class="flex flex-col gap-5">
            @csrf
            <input type="hidden" name="expected_state" value="{{ $subscription->transitionState() }}">
            <input type="hidden" name="operation_key" value="{{ $oldText('operation_key', $operationKey) }}">
            <flux:select name="plan_uuid" label="Nouvelle offre" required>
                <option value="">Choisir une offre</option>
                @foreach ($plans as $plan)
                    <option value="{{ $plan->uuid }}" @selected(old('plan_uuid') === $plan->uuid)>{{ $plan->name }} — v{{ $plan->version }} — {{ $plan->monthly_price }} DZD/mois, {{ $plan->annual_price }} DZD/an</option>
                @endforeach
            </flux:select>
            @foreach ($plans as $plan)
                <input type="hidden" name="quoted_prices[{{ $plan->uuid }}][1]" value="{{ $plan->monthly_price }}">
                <input type="hidden" name="quoted_prices[{{ $plan->uuid }}][2]" value="{{ $plan->annual_price }}">
            @endforeach
            <flux:select name="period" label="Périodicité" required>
                @foreach (\App\Enums\Central\Subscriptions\PeriodEnum::cases() as $period)
                    <option value="{{ $period->value }}" @selected($oldText('period', 1) === (string) $period->value)>{{ $period->label() }}</option>
                @endforeach
            </flux:select>
            @include('central.pages.owner.billing.subscriptions.payment-fields')
            <flux:input name="account" label="Compte destinataire contrôlé (versement externe)" :value="$oldText('account')" maxlength="255" />
            <flux:input name="reference" label="Référence réelle du contrôle" :value="$oldText('reference')" required maxlength="255" />
            <flux:input type="datetime-local" name="occurred_at" label="Date réelle de réception" :value="$oldText('occurred_at')" required />
            <flux:checkbox name="received" value="1" label="J’atteste avoir contrôlé la réception du montant intégral." required />
            <div class="flex gap-3"><flux:button type="submit" variant="primary">Confirmer le paiement et changer l’offre</flux:button>
                @can('view', $subscription)<flux:button :href="route('central.subscriptions.show', $subscription)">Retour</flux:button>@endcan
            </div>
        </form>
    </section>
</x-layouts::app>
