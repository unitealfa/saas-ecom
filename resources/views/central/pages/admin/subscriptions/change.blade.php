@php($oldText = static fn (string $key, mixed $default = null): string => is_scalar($value = old($key, $default)) ? (string) $value : '')
<x-layouts::app :title="__('Changer l’offre')">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Changer l’offre</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">Une offre à 0,00 DZD est approuvée lors de l’enregistrement. Pour une offre payante, confirmez la réception du montant intégral.</p></header>
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <form method="POST" enctype="multipart/form-data" action="{{ route('central.subscriptions.change', $subscription) }}" class="aydra-users-panel flex flex-col gap-5 p-5 sm:p-6"
            x-data="{ plan: @js($oldText('plan_uuid')), period: @js($oldText('period', 1)), prices: @js($plans->mapWithKeys(fn ($offer) => [$offer->uuid => [1 => $offer->monthly_price, 2 => $offer->annual_price]])), get isFree() { return this.prices[this.plan]?.[this.period] === '0.00' } }">
            @csrf
            <input type="hidden" name="expected_state" value="{{ $subscription->transitionState() }}">
            <input type="hidden" name="operation_key" value="{{ $oldText('operation_key', $operationKey) }}">
            <flux:select name="plan_uuid" label="Nouvelle offre" x-model="plan" required>
                <option value="">Choisir une offre</option>
                @foreach ($plans as $plan)
                    <option value="{{ $plan->uuid }}" @selected(old('plan_uuid') === $plan->uuid)>{{ $plan->name }} — v{{ $plan->version }} — {{ $plan->monthly_price }} DZD/mois, {{ $plan->annual_price }} DZD/an</option>
                @endforeach
            </flux:select>
            @foreach ($plans as $plan)
                <input type="hidden" name="quoted_prices[{{ $plan->uuid }}][1]" value="{{ $plan->monthly_price }}">
                <input type="hidden" name="quoted_prices[{{ $plan->uuid }}][2]" value="{{ $plan->annual_price }}">
            @endforeach
            <flux:select name="period" label="Périodicité" x-model="period" required>
                @foreach (\App\Enums\Central\Subscriptions\PeriodEnum::cases() as $period)
                    <option value="{{ $period->value }}" @selected($oldText('period', 1) === (string) $period->value)>{{ $period->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select name="transition_mode" label="Début de la nouvelle période">
                @foreach (\App\Enums\Central\Subscriptions\TransitionModeEnum::cases() as $mode)
                    <option value="{{ $mode->value }}" @selected($oldText('transition_mode', 2) === (string) $mode->value)>{{ $mode->label() }}</option>
                @endforeach
            </flux:select>
            <template x-if="plan && ! isFree">
                <div class="flex flex-col gap-5">
                    @include('central.pages.owner.billing.subscriptions.payment-fields', ['hideTransition' => true])
                    <flux:input name="account" label="Compte destinataire contrôlé (versement externe)" :value="$oldText('account')" maxlength="255" />
                    <flux:input name="reference" label="Référence réelle du contrôle" :value="$oldText('reference')" required maxlength="255" />
                    <flux:input type="datetime-local" name="occurred_at" label="Date réelle de réception" :value="$oldText('occurred_at')" required />
                    <flux:checkbox name="received" value="1" label="J’atteste avoir contrôlé la réception du montant intégral." required />
                </div>
            </template>
            <div class="flex flex-wrap gap-3"><flux:button type="submit" variant="primary" class="max-w-full whitespace-normal">Valider le changement d’offre</flux:button>
                @can('view', $subscription)<flux:button :href="route('central.subscriptions.show', $subscription)">Retour</flux:button>@endcan
            </div>
        </form>
    </section>
</x-layouts::app>
