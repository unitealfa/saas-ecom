@php
    $oldText = static fn (string $key, mixed $default = null): string => is_scalar($value = old($key, $default)) ? (string) $value : '';
@endphp
<x-layouts::app title="Mon abonnement et les offres">
    <section class="mx-auto flex w-full max-w-5xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-4"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight">Mon abonnement et les offres</h1><p class="aydra-muted text-sm">Consultez vos périodes et choisissez l’offre adaptée à votre activité.</p></div><flux:button :href="route('owner.subscription-requests.index')">Mes demandes d’abonnement</flux:button></header>
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        <flux:text>Les offres à 0,00 DZD attendent une validation administrative. Pour les offres payantes, le montant est réglé en une fois.</flux:text>
        @php($pending = $subscriptions->contains(fn ($subscription) => $subscription->status === \App\Enums\Central\Subscriptions\StatusEnum::SCHEDULED && ($subscription->period_ends_at === null || $subscription->period_ends_at->isFuture())))
        @if ($pending)<flux:callout>Une période est déjà prévue ou en attente. Vous pouvez modifier ou annuler une demande non payée ci-dessous, puis choisir une autre offre.</flux:callout>@endif
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($plans as $plan)<article class="aydra-users-panel p-5 sm:p-6">
                <flux:heading>{{ $plan['name'] }}</flux:heading><flux:text>{{ $plan['description'] }}</flux:text>
                @if ($plan['is_default'])<flux:text>Par défaut — attribution gratuite</flux:text>@else<flux:text>{{ $plan['monthly_price'] }} DZD/mois — {{ $plan['annual_price'] }} DZD/an</flux:text>@endif
                @foreach ($plan['features'] as $feature)<flux:text>{{ $feature['name'] }} : {{ $feature['value'] }}</flux:text>@endforeach
                @if ($plan['is_default'])
                    @if ($currentSubscription && $defaultPlan?->uuid === $plan['uuid'] && (! $currentSubscription->isFree() || $currentSubscription->plan_id !== $defaultPlan->id))
                        <details class="mt-4 space-y-3">
                            <summary>Passer au plan par défaut</summary>
                            <flux:callout variant="warning">Vous mettrez définitivement fin à votre abonnement actuel et perdrez ses avantages. Le plan par défaut sera activé immédiatement, sans remboursement.</flux:callout>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <flux:heading>Offre actuelle · {{ $currentSubscription->plan->name }}</flux:heading>
                                    <flux:text>Fin prévue {{ $currentSubscription->effectiveEndsAt()?->format('d/m/Y') ?? 'sans échéance' }}</flux:text>
                                    @foreach ($currentSubscription->plan->features->where('is_active', true) as $benefit)
                                        <flux:text>{{ $benefit->feature->name }}@if ($benefit->limit !== null) — {{ $benefit->limit }} {{ $benefit->feature->unit }}@endif</flux:text>
                                    @endforeach
                                </div>
                                <div>
                                    <flux:heading>Plan par défaut · {{ $plan['name'] }}</flux:heading>
                                    @foreach ($defaultPlan->features->where('is_active', true) as $benefit)
                                        <flux:text>{{ $benefit->feature->name }}@if ($benefit->limit !== null) — {{ $benefit->limit }} {{ $benefit->feature->unit }}@endif</flux:text>
                                    @endforeach
                                </div>
                            </div>
                            <form method="POST" action="{{ route('owner.subscriptions.switch-to-default', $currentSubscription) }}" class="flex flex-col gap-3">
                                @csrf
                                <input type="hidden" name="expected_state" value="{{ $currentSubscription->transitionState() }}">
                                <input type="hidden" name="plan_uuid" value="{{ $defaultPlan->uuid }}">
                                <input type="hidden" name="expected_plan_state" value="{{ $defaultPlan->compositionState($defaultPlan->features) }}">
                                <flux:checkbox name="confirmed" value="1" required label="J’accepte de perdre les avantages de mon offre actuelle." />
                                <flux:button type="submit" :disabled="$pending">Confirmer le passage au plan par défaut</flux:button>
                            </form>
                        </details>
                    @endif
                @else
                    <form method="POST" enctype="multipart/form-data" action="{{ route('owner.subscriptions.store') }}" class="mt-4 flex flex-col gap-3" x-data="{ period: @js($oldText('plan_uuid') === $plan['uuid'] ? $oldText('period', '1') : '1') }">
                        @csrf<input type="hidden" name="plan_uuid" value="{{ $plan['uuid'] }}">
                        <input type="hidden" name="operation_id" value="{{ $oldText('plan_uuid') === $plan['uuid'] ? $oldText('operation_id', (string) \Illuminate\Support\Str::uuid()) : (string) \Illuminate\Support\Str::uuid() }}">
                        <input type="hidden" name="expected_amount" value="{{ $plan['monthly_price'] }}" x-bind:value="period === '1' ? '{{ $plan['monthly_price'] }}' : '{{ $plan['annual_price'] }}'">
                        <flux:select name="period" label="Période" x-model="period" required>
                            <option value="1">Un mois — {{ $plan['monthly_price'] }} DZD</option><option value="2">Un an — {{ $plan['annual_price'] }} DZD</option>
                        </flux:select>
                        <flux:select name="transition_mode" label="Début de la nouvelle période">
                            @foreach (\App\Enums\Central\Subscriptions\TransitionModeEnum::cases() as $mode)
                                <option value="{{ $mode->value }}" @selected(($oldText('plan_uuid') === $plan['uuid'] ? $oldText('transition_mode', 2) : '2') === (string) $mode->value)>{{ $mode === \App\Enums\Central\Subscriptions\TransitionModeEnum::IMMEDIATE ? 'Dès confirmation' : $mode->label() }}</option>
                            @endforeach
                        </flux:select>
                        @if ($plan['monthly_price'] !== '0.00' || $plan['annual_price'] !== '0.00')
                            <template x-if="(period === '1' ? @js($plan['monthly_price']) : @js($plan['annual_price'])) !== '0.00'">
                                <div class="flex flex-col gap-3">
                                    @include('central.pages.owner.billing.subscriptions.payment-fields', ['hideTransition' => true])
                                </div>
                            </template>
                        @endif
                        <flux:button type="submit" :disabled="$pending">Créer ma demande</flux:button>
                    </form>
                @endif
            </article>@endforeach
        </div>
        <flux:heading>Mes périodes</flux:heading>
        @forelse ($subscriptions as $subscription)
            <article class="aydra-users-panel flex flex-col items-start gap-3 p-5 sm:p-6">
                <flux:button :href="route('owner.subscriptions.show', $subscription)">{{ $subscription->plan->name }} — {{ $subscription->status->label() }} — {{ $subscription->effectiveStartsAt()->format('d/m/Y') }}</flux:button>
                @can('startNow', $subscription)
                    <flux:button :href="route('owner.subscriptions.show', $subscription).'#demarrer'">Appliquer ce plan maintenant</flux:button>
                @endcan
                @if ($subscription->request?->current_subscription_id === $subscription->id)
                    <div class="flex flex-wrap gap-2">
                        <flux:button :href="route('owner.subscription-requests.show', $subscription->request)">Consulter la demande</flux:button>
                        @can('update', $subscription->request)
                            <flux:button :href="route('owner.subscription-requests.show', $subscription->request).'#modifier'">Modifier l’offre ou la période</flux:button>
                        @endcan
                        @can('cancel', $subscription->request)
                            <form method="POST" action="{{ route('owner.subscription-requests.cancel', $subscription->request) }}">
                                @csrf
                                <input type="hidden" name="expected_state" value="{{ $subscription->request->managementState() }}">
                                <flux:button type="submit" variant="danger">Annuler la demande non payée</flux:button>
                            </form>
                        @endcan
                    </div>
                @endif
            </article>
        @empty<flux:text>Aucune période enregistrée.</flux:text>@endforelse
    </section>
</x-layouts::app>
