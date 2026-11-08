@php
    $oldText = static fn (string $key, mixed $default = null): string => is_scalar($value = old($key, $default)) ? (string) $value : '';
@endphp
<x-layouts::app title="Mon abonnement et les offres">
    <section class="mx-auto flex w-full max-w-5xl flex-col gap-6">
        <flux:heading size="xl" level="1">Mon abonnement et les offres</flux:heading>
        <flux:button :href="route('owner.subscription-requests.index')">Mes demandes d’abonnement</flux:button>
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        <flux:text>Une période se paie intégralement. Les droits actuels sont conservés jusqu’à leur fin ; une nouvelle période n’est activée qu’après réception et vérification du paiement.</flux:text>
        @php($pending = $subscriptions->contains(fn ($subscription) => $subscription->status === \App\Enums\Central\Subscriptions\StatusEnum::SCHEDULED && ($subscription->period_ends_at === null || $subscription->period_ends_at->isFuture())))
        @if ($pending)<flux:callout>Une période est déjà prévue ou en attente. Consultez-la ci-dessous avant de choisir une autre offre.</flux:callout>@endif
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($plans as $plan)<article class="rounded-lg border border-zinc-200 p-5 dark:border-zinc-700">
                <flux:heading>{{ $plan['name'] }}</flux:heading><flux:text>{{ $plan['description'] }}</flux:text>
                <flux:text>{{ $plan['monthly_price'] }} DZD/mois — {{ $plan['annual_price'] }} DZD/an</flux:text>
                @foreach ($plan['features'] as $feature)<flux:text>{{ $feature['name'] }} : {{ $feature['value'] }}</flux:text>@endforeach
                @if ($plan['code'] === 'free')<flux:text>Offre gratuite : aucun paiement à effectuer.</flux:text>
                @else
                    <form method="POST" action="{{ route('owner.subscriptions.store') }}" class="mt-4 flex flex-col gap-3" x-data="{ period: @js($oldText('plan_uuid') === $plan['uuid'] ? $oldText('period', '1') : '1') }">
                        @csrf<input type="hidden" name="plan_uuid" value="{{ $plan['uuid'] }}">
                        <input type="hidden" name="operation_id" value="{{ $oldText('plan_uuid') === $plan['uuid'] ? $oldText('operation_id', (string) \Illuminate\Support\Str::uuid()) : (string) \Illuminate\Support\Str::uuid() }}">
                        <input type="hidden" name="expected_amount" value="{{ $plan['monthly_price'] }}" x-bind:value="period === '1' ? '{{ $plan['monthly_price'] }}' : '{{ $plan['annual_price'] }}'">
                        <flux:select name="period" label="Période à régler en une fois" x-model="period">
                            <option value="1">Un mois — {{ $plan['monthly_price'] }} DZD</option><option value="2">Un an — {{ $plan['annual_price'] }} DZD</option>
                        </flux:select>
                        <flux:select name="payment_choice" label="Comment souhaitez-vous payer ?" required>
                            <option value="1">Espèces au bureau Aydra</option>
                            <option value="2">Versement externe avec justificatif</option>
                        </flux:select>
                        <flux:button type="submit" :disabled="$pending">Créer ma demande et obtenir ma facture</flux:button>
                    </form>
                @endif
            </article>@endforeach
        </div>
        <flux:heading>Mes périodes</flux:heading>
        @forelse ($subscriptions as $subscription)
            <flux:button :href="route('owner.subscriptions.show', $subscription)">{{ $subscription->plan->name }} — {{ $subscription->status->label() }} — {{ $subscription->period_starts_at->format('d/m/Y') }}</flux:button>
        @empty<flux:text>Aucune période enregistrée.</flux:text>@endforelse
    </section>
</x-layouts::app>
