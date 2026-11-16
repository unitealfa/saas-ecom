@if ($currentSubscription)
    <div class="rounded-lg border border-amber-300 p-4 dark:border-amber-700">
        <flux:heading>Plan actuel : {{ $currentSubscription->plan->name }}</flux:heading>
        <flux:text>Fin prévue : {{ $currentSubscription->effectiveEndsAt()?->format('d/m/Y H:i') ?? 'Sans date de fin' }}.</flux:text>
        <ul class="mt-2 list-inside list-disc text-sm">
            @foreach ($currentSubscription->plan->features as $grant)
                @if ($grant->is_active && $grant->feature?->is_active)
                    <li>{{ $grant->feature->name }} : {{ $grant->feature->value_type === \App\Enums\Central\Features\ValueTypeEnum::BOOLEAN ? 'Inclus' : ($grant->limit === null ? 'Sans limite' : $grant->limit.' '.$grant->feature->unit) }}</li>
                @endif
            @endforeach
        </ul>
        <flux:text>Ces droits seront arrêtés lors du remplacement. Les nouveaux quotas seront appliqués ; les données et boutiques seront conservées, avec accès limité si le nouveau quota est inférieur.</flux:text>
    </div>
@else
    <flux:text>Aucun plan en cours ne sera interrompu.</flux:text>
@endif
