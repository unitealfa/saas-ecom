<x-layouts::app :title="__('Tableau de bord administratif')">
    <section class="mx-auto flex w-full max-w-screen-2xl flex-col gap-7">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-2">
                <h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Vue d’ensemble</h1>
                <p class="aydra-muted max-w-2xl text-sm sm:text-base">Les chiffres essentiels pour suivre votre plateforme.</p>
            </div>
            <p class="aydra-dashboard-date">Consulté le {{ now()->utc()->format('d/m/Y à H:i') }} UTC</p>
        </header>

        @if (session('status'))
            <flux:callout>{{ session('status') }}</flux:callout>
        @endif
        @if (session('warning'))
            <flux:callout variant="warning">{{ session('warning') }}</flux:callout>
        @endif

        @if ($overview !== null)
            @php
                $groups = [
                    [
                        'title' => 'Comptes commerçants', 'icon' => 'users', 'counts' => $overview['merchants'],
                        'statuses' => \App\Enums\Central\Users\StatusEnum::cases(),
                        'description' => 'Comptes non archivés, hors personnel SaaS.',
                    ],
                    [
                        'title' => 'Boutiques', 'icon' => 'building-storefront', 'counts' => $overview['shops'],
                        'statuses' => \App\Enums\Central\Tenants\StatusEnum::cases(),
                        'description' => 'Boutiques non archivées.',
                    ],
                    [
                        'title' => 'Abonnements', 'icon' => 'credit-card', 'counts' => $overview['subscriptions'],
                        'statuses' => \App\Enums\Central\Subscriptions\StatusEnum::cases(),
                        'description' => 'Abonnements enregistrés, hors échéances.',
                    ],
                ];
                $chartColors = ['#3459da', '#5284e8', '#359daf', '#8b6cb3', '#c08334', '#78869d', '#b76377'];
            @endphp

            <div class="aydra-overview">
                <div class="aydra-overview-header">
                    <div>
                        <h2 class="aydra-heading text-lg font-semibold">Activité de la plateforme</h2>
                        <p class="aydra-muted mt-1 text-sm">Répartition actuelle par statut.</p>
                    </div>
                </div>
                <div class="aydra-overview-grid">
                    @foreach ($groups as $group)
                        @php($total = array_sum($group['counts']))
                        <article class="aydra-overview-column flex flex-col gap-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="aydra-heading text-base font-semibold">{{ $group['title'] }}</h3>
                                    <p class="aydra-muted mt-1 text-xs">Répartition par statut</p>
                                </div>
                                <span class="aydra-icon" aria-hidden="true"><flux:icon :icon="$group['icon']" class="size-5" /></span>
                            </div>
                            <p class="sr-only">Total : {{ $total }}</p>
                            <div class="aydra-chart" aria-hidden="true">
                                <svg viewBox="0 0 120 120" class="aydra-chart-ring">
                                    <circle class="aydra-chart-track" cx="60" cy="60" r="48" pathLength="100" />
                                    @php($offset = 0)
                                    @foreach ($group['statuses'] as $status)
                                        @php($count = (int) ($group['counts'][$status->value] ?? 0))
                                        @php($portion = $total > 0 ? $count * 100 / $total : 0)
                                        @if ($portion > 0)
                                            <circle cx="60" cy="60" r="48" pathLength="100" fill="none"
                                                stroke="{{ $chartColors[$loop->index % count($chartColors)] }}" stroke-width="10"
                                                stroke-dasharray="{{ $portion }} {{ 100 - $portion }}" stroke-dashoffset="{{ -$offset }}" />
                                        @endif
                                        @php($offset += $portion)
                                    @endforeach
                                </svg>
                                <div class="aydra-chart-center"><span>{{ $total }}</span><small>total</small></div>
                            </div>
                            <ul class="aydra-status-list space-y-2.5 pt-4" aria-label="Répartition : {{ $group['title'] }}">
                                @foreach ($group['statuses'] as $status)
                                    @php($count = (int) ($group['counts'][$status->value] ?? 0))
                                    <li class="flex items-center justify-between gap-3 text-sm">
                                        <span class="aydra-muted flex items-center gap-2"><span class="aydra-chart-key" style="background-color: {{ $chartColors[$loop->index % count($chartColors)] }}" aria-hidden="true"></span>{{ $status->label() }}</span>
                                        <span class="aydra-heading font-medium tabular-nums">{{ $count }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="aydra-muted mt-auto text-xs">{{ $group['description'] }}</p>
                        </article>
                    @endforeach
                </div>
                <p class="aydra-overview-note">Les statuts reflètent les données enregistrées au moment de la consultation.</p>
            </div>
        @endif

        @if ($payments !== null)
            <article class="aydra-financial p-5 sm:p-6">
                <div class="flex items-start gap-4">
                    <span class="aydra-icon shrink-0" aria-hidden="true"><flux:icon.banknotes class="size-5" /></span>
                    <div class="min-w-0 flex-1 space-y-3">
                        <div>
                            <h2 class="aydra-heading text-lg font-semibold">Paiements vérifiés</h2>
                            <p class="aydra-muted mt-1 text-sm">Montants bruts enregistrés, regroupés par devise depuis le début de l’activité.</p>
                        </div>
                        @forelse ($payments as $payment)
                            <div class="flex flex-wrap items-baseline justify-between gap-2 border-t border-slate-100 pt-3 dark:border-slate-700">
                                <span class="aydra-heading text-2xl font-semibold tabular-nums">{{ $payment['total'] }} {{ $payment['currency'] }}</span>
                                <span class="aydra-muted text-sm">{{ $payment['count'] }} paiement(s)</span>
                            </div>
                        @empty
                            <p class="aydra-muted rounded-lg bg-slate-50 p-4 text-sm dark:bg-slate-800">Aucun paiement vérifié enregistré.</p>
                        @endforelse
                        <p class="aydra-muted text-xs">Hors remboursements et corrections : ces montants ne représentent ni le chiffre d’affaires net ni le solde restant dû.</p>
                    </div>
                </div>
            </article>
        @endif
    </section>
</x-layouts::app>
