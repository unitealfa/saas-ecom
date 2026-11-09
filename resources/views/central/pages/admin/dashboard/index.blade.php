<x-layouts::app :title="__('Tableau de bord administratif')">
    <section class="flex flex-col gap-6">
        <div class="space-y-2">
            <flux:heading size="xl" level="1">Tableau de bord administratif</flux:heading>
            <flux:text>Consultation effectuée le {{ now()->utc()->format('d/m/Y à H:i') }} UTC. Indicateurs calculés depuis les données centrales, sans accès aux bases des boutiques ni instantané comptable garanti.</flux:text>
        </div>

        @if ($overview !== null)
            @php
                $groups = [
                    [
                        'title' => 'Comptes commerçants',
                        'counts' => $overview['merchants'],
                        'statuses' => \App\Enums\Central\Users\StatusEnum::cases(),
                        'source' => 'Source : users et rôles centraux. Comptes non archivés sans rôle SaaS.',
                    ],
                    [
                        'title' => 'Boutiques',
                        'counts' => $overview['shops'],
                        'statuses' => \App\Enums\Central\Tenants\StatusEnum::cases(),
                        'source' => 'Source : tenants. Boutiques non archivées, sans lecture de leur base.',
                    ],
                    [
                        'title' => 'Abonnements',
                        'counts' => $overview['subscriptions'],
                        'statuses' => \App\Enums\Central\Subscriptions\StatusEnum::cases(),
                        'source' => 'Source : subscriptions, type abonnement uniquement (record_type = 1). Les échéances ne sont pas comptées.',
                    ],
                ];
            @endphp

            <div class="grid gap-4 lg:grid-cols-3">
                @foreach ($groups as $group)
                    @php($total = array_sum($group['counts']))
                    <div class="rounded-xl border p-5 space-y-4">
                        <div class="space-y-1">
                            <flux:heading level="2">{{ $group['title'] }}</flux:heading>
                            <flux:text>Total : {{ $total }}</flux:text>
                        </div>
                        <div class="space-y-3">
                            @foreach ($group['statuses'] as $status)
                                @php($count = (int) ($group['counts'][$status->value] ?? 0))
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <flux:text>{{ $status->label() }}</flux:text>
                                        <flux:text>{{ $count }}</flux:text>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700"
                                        role="progressbar"
                                        aria-label="{{ $group['title'] }} — {{ $status->label() }}"
                                        aria-valuenow="{{ $count }}"
                                        aria-valuemin="0"
                                        aria-valuemax="{{ $total }}">
                                        <div class="h-full rounded-full bg-zinc-700 dark:bg-zinc-300"
                                            style="width: {{ $total > 0 ? round($count * 100 / $total, 2) : 0 }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <flux:text class="text-xs">{{ $group['source'] }}</flux:text>
                    </div>
                @endforeach
            </div>
            <flux:text>Les statuts affichés sont les valeurs enregistrées à la consultation ; ils ne déclenchent pas de résolution d’abonnement.</flux:text>
        @endif

        @if ($payments !== null)
            <div class="rounded-xl border p-5 space-y-3">
                <flux:heading level="2">Paiements vérifiés</flux:heading>
                <flux:text>Source : saas_transfers (record_type = 1). Paiements VERIFIED datés et non contrepassés, depuis le début de l’activité, regroupés par devise.</flux:text>
                <flux:text>Montants bruts enregistrés, hors remboursements et corrections : ils ne représentent ni le chiffre d’affaires net ni le solde restant dû.</flux:text>
                @forelse ($payments as $payment)
                    <flux:text>{{ $payment['total'] }} {{ $payment['currency'] }} — {{ $payment['count'] }} paiement(s)</flux:text>
                @empty
                    <flux:text>Aucun paiement vérifié enregistré.</flux:text>
                @endforelse
            </div>
        @endif
    </section>
</x-layouts::app>
