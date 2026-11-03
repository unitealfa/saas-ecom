<x-layouts::app :title="__('Changer l’offre')">
    <section class="mx-auto flex max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Changer l’offre</flux:heading>
        <flux:text>La nouvelle offre commencera le {{ $subscription->period_ends_at?->format('d/m/Y H:i') ?? 'à une date non renseignée' }}, à la fin de la période actuelle. Son montant sera celui du tarif choisi ci-dessous.</flux:text>
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <form method="POST" action="{{ route('central.subscriptions.change', $subscription) }}" class="flex flex-col gap-5">
            @csrf
            <input type="hidden" name="expected_state" value="{{ $subscription->transitionState() }}">
            <input type="hidden" name="operation_key" value="{{ old('operation_key', $operationKey) }}">
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
                    <option value="{{ $period->value }}" @selected((string) old('period', 1) === (string) $period->value)>{{ $period->label() }}</option>
                @endforeach
            </flux:select>
            <div class="flex gap-3"><flux:button type="submit" variant="primary">Programmer le changement</flux:button>
                @can('view', $subscription)<flux:button :href="route('central.subscriptions.show', $subscription)">Retour</flux:button>@endcan
            </div>
        </form>
    </section>
</x-layouts::app>
