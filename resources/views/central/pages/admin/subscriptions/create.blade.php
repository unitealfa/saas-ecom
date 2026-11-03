<x-layouts::app :title="__('Attribuer un abonnement')">
    <section class="mx-auto flex max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Attribuer un abonnement</flux:heading>
        <flux:text>L’abonnement commence immédiatement. Son montant correspond au tarif mensuel ou annuel de l’offre. Le renouvellement automatique est désactivé.</flux:text>
        <flux:text>Une offre gratuite en cours sera remplacée et conservée dans l’historique. Un abonnement payant en cours ou prévu bloque l’attribution.</flux:text>
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <form method="POST" action="{{ route('central.subscriptions.store') }}" class="flex flex-col gap-5">
            @csrf
            <input type="hidden" name="operation_key" value="{{ old('operation_key', $operationKey) }}">
            <flux:input name="owner_uuid" label="UUID du commerçant" :value="old('owner_uuid', request('owner'))" required />
            <flux:select name="plan_uuid" label="Offre commercialisée" required>
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
            <div class="flex gap-3"><flux:button type="submit" variant="primary">Attribuer</flux:button>
                @can('viewAny', \App\Models\Central\Subscriptions\Subscription::class)<flux:button :href="route('central.subscriptions.index')">Retour</flux:button>@endcan
            </div>
        </form>
    </section>
</x-layouts::app>
