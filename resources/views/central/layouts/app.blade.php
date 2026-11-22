<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main :inset="app(\App\Services\Central\Authorization\CentralUserAccess::class)->isStaff(auth('central')->user())">
        @php
            $owner = auth('central')->user();
            $endingSubscription = $owner && ! app(\App\Services\Central\Authorization\CentralUserAccess::class)->isStaff($owner)
                ? \App\Models\Central\Subscriptions\Subscription::where('user_id', $owner->id)
                    ->whereIn('status', [\App\Enums\Central\Subscriptions\StatusEnum::ACTIVE, \App\Enums\Central\Subscriptions\StatusEnum::TRIAL])
                    ->where('agreed_amount', '>', 0)
                    ->whereRaw('COALESCE(effective_starts_at, period_starts_at) <= ?', [now()])
                    ->whereRaw('COALESCE(effective_ends_at, period_ends_at) >= ?', [today()])
                    ->whereRaw('COALESCE(effective_ends_at, period_ends_at) < ?', [today()->addDays(8)])
                    ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', now()))
                    ->orderByDesc('id')->first()
                : null;
            $daysRemaining = $endingSubscription ? (int) today()->diffInDays($endingSubscription->effectiveEndsAt()->startOfDay()) : null;
        @endphp
        @if ($endingSubscription)
            <div x-data="{ open: true }" x-show="open" role="dialog" aria-modal="true" aria-label="Fin de votre abonnement" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <div class="rounded-lg bg-white p-6 shadow-xl dark:bg-zinc-800">
                    <p>Votre plan se termine dans {{ $daysRemaining }} {{ $daysRemaining === 1 ? 'jour' : 'jours' }}.</p>
                    <div class="mt-4 flex gap-3">
                        <a href="{{ route('owner.subscriptions.index') }}">Renouveler</a>
                        <button type="button" x-on:click="open = false">Fermer</button>
                    </div>
                </div>
            </div>
        @endif
        <x-central::components.breadcrumbs />
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
