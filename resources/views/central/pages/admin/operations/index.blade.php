<x-layouts::app :title="__('Exploitation centrale')">
    <section class="mx-auto flex w-full max-w-screen-2xl flex-col gap-6">
        <header class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Exploitation centrale</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">Suivez les opérations centrales et lancez les traitements autorisés.</p></header>
        @error('cursor')<flux:callout variant="danger" role="alert">{{ $message }}</flux:callout>@enderror

        @php($result = session('operations_result'))
        @if ($result && (($result['type'] === 'subscriptions' && \Illuminate\Support\Facades\Gate::allows('reconcileSubscriptions', \App\Models\User::class)) || ($result['type'] === 'payments' && \Illuminate\Support\Facades\Gate::allows('reconcilePayments', \App\Models\User::class))))
            <flux:callout>
                <flux:callout.heading>Résultat du lot</flux:callout.heading>
                <flux:callout.text>
                    @if ($result['type'] === 'subscriptions') Comptes traités : {{ $result['processed'] }}. Échecs : {{ $result['failed'] }}.
                    @else Paiements examinés : {{ $result['processed'] }}. Anomalies : {{ count($result['anomalies']) }}. Aucun paiement n’a été confirmé ou modifié. @endif
                </flux:callout.text>
                @if ($result['type'] === 'subscriptions') @foreach ($result['failed_owners'] as $ownerUuid)<flux:text>Compte à examiner : {{ $ownerUuid }}</flux:text>@endforeach @endif
                @if ($result['type'] === 'payments') @foreach ($result['anomalies'] as $anomaly)<flux:text>{{ $anomaly['uuid'] }} — {{ implode(', ', $anomaly['reasons']) }}</flux:text>@endforeach @endif
            </flux:callout>
        @endif

        @canany(['reconcileSubscriptions', 'reconcilePayments'], \App\Models\User::class)
            <section class="aydra-users-panel p-5 sm:p-6" aria-labelledby="operation-actions"><h2 id="operation-actions" class="aydra-heading text-lg font-semibold">Traitements</h2><p class="aydra-muted mt-1 text-sm">Lancez ou poursuivez un traitement selon vos autorisations.</p>
                <div class="mt-5 grid gap-4 lg:grid-cols-2">
                    @can('reconcileSubscriptions', \App\Models\User::class)
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700"><h3 class="aydra-heading font-semibold">Abonnements</h3><p class="aydra-muted mt-1 text-sm">Appliquez les transitions arrivées à échéance et recalculez les boutiques concernées.</p><div class="mt-4 flex flex-wrap gap-2"><form method="POST" action="{{ route('central.operations.subscriptions.reconcile') }}">@csrf<flux:button type="submit">Réconcilier les abonnements</flux:button></form>@if (isset($result) && $result['type'] === 'subscriptions' && $result['next_cursor'])<form method="POST" action="{{ route('central.operations.subscriptions.reconcile') }}">@csrf<input type="hidden" name="cursor" value="{{ $result['next_cursor'] }}"><flux:button type="submit" variant="primary">Continuer les abonnements</flux:button></form>@endif</div></div>
                    @endcan
                    @can('reconcilePayments', \App\Models\User::class)
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700"><h3 class="aydra-heading font-semibold">Paiements</h3><p class="aydra-muted mt-1 text-sm">Recherchez les anomalies et produisez un bilan sans confirmer de paiement.</p><div class="mt-4 flex flex-wrap gap-2"><form method="POST" action="{{ route('central.operations.payments.reconcile') }}">@csrf<flux:button type="submit">Rapprocher les paiements</flux:button></form>@if (isset($result) && $result['type'] === 'payments' && $result['next_cursor'])<form method="POST" action="{{ route('central.operations.payments.reconcile') }}">@csrf<input type="hidden" name="cursor" value="{{ $result['next_cursor'] }}"><flux:button type="submit" variant="primary">Continuer les paiements</flux:button></form>@endif</div></div>
                    @endcan
                </div>
            </section>
        @endcanany

        @if ($deployments !== null)
            <section class="aydra-users-panel" aria-labelledby="operation-deployments"><div class="aydra-users-toolbar"><div><h2 id="operation-deployments" class="aydra-heading text-lg font-semibold">Déploiements de boutiques</h2><p class="aydra-muted mt-1 text-sm">{{ $deployments->total() }} {{ $deployments->total() === 1 ? 'déploiement enregistré' : 'déploiements enregistrés' }}</p></div></div>
                <div class="aydra-users-table"><flux:table class="whitespace-normal"><flux:table.columns><flux:table.column>Référence</flux:table.column><flux:table.column>Boutique</flux:table.column><flux:table.column>Opération</flux:table.column><flux:table.column>État</flux:table.column><flux:table.column>Tentative</flux:table.column><flux:table.column>Créé le</flux:table.column><flux:table.column>Terminé le</flux:table.column></flux:table.columns><flux:table.rows>
                    @forelse ($deployments as $deployment)
                        <flux:table.row :key="$deployment->uuid"><flux:table.cell variant="strong" class="break-all">{{ $deployment->uuid }}</flux:table.cell><flux:table.cell class="break-all">{{ $deployment->shop_uuid }}</flux:table.cell><flux:table.cell>{{ \App\Enums\Central\Deployments\OperationEnum::tryFrom((int) $deployment->operation)?->label() ?? 'Type inconnu' }}</flux:table.cell><flux:table.cell><flux:badge size="sm">{{ \App\Enums\Central\Deployments\StatusEnum::tryFrom((int) $deployment->status)?->label() ?? 'État inconnu' }}</flux:badge></flux:table.cell><flux:table.cell>{{ $deployment->attempt_number }}</flux:table.cell><flux:table.cell class="whitespace-nowrap">{{ $deployment->created_at }}</flux:table.cell><flux:table.cell class="whitespace-nowrap">{{ $deployment->ended_at ?? '—' }}</flux:table.cell></flux:table.row>
                    @empty <flux:table.row><flux:table.cell colspan="7"><div class="aydra-users-empty"><p class="aydra-heading font-medium">Aucun déploiement enregistré.</p></div></flux:table.cell></flux:table.row> @endforelse
                </flux:table.rows></flux:table>
                @if ($deployments->hasPages())<nav aria-label="Pagination des déploiements" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700"><span class="aydra-muted text-sm">Page {{ $deployments->currentPage() }} sur {{ $deployments->lastPage() }}</span><div class="flex gap-2">@if ($deployments->onFirstPage())<flux:button disabled icon="chevron-left">Précédent</flux:button>@else<flux:button :href="$deployments->previousPageUrl()" icon="chevron-left">Précédent</flux:button>@endif @if ($deployments->hasMorePages())<flux:button :href="$deployments->nextPageUrl()" icon:trailing="chevron-right">Suivant</flux:button>@else<flux:button disabled icon:trailing="chevron-right">Suivant</flux:button>@endif</div></nav>@endif
                </div>
            </section>
            <section class="aydra-users-panel" aria-labelledby="operation-deliveries"><div class="aydra-users-toolbar"><div><h2 id="operation-deliveries" class="aydra-heading text-lg font-semibold">Envois de documents</h2><p class="aydra-muted mt-1 text-sm">{{ $deliveries->total() }} {{ $deliveries->total() === 1 ? 'envoi enregistré' : 'envois enregistrés' }}</p></div></div>
                <div class="aydra-users-table"><flux:table class="whitespace-normal"><flux:table.columns><flux:table.column>Référence</flux:table.column><flux:table.column>Canal</flux:table.column><flux:table.column>État</flux:table.column><flux:table.column>Tentatives</flux:table.column><flux:table.column>Créé le</flux:table.column><flux:table.column>Accepté le</flux:table.column></flux:table.columns><flux:table.rows>
                    @forelse ($deliveries as $delivery)
                        <flux:table.row :key="$delivery->uuid"><flux:table.cell variant="strong" class="break-all">{{ $delivery->uuid }}</flux:table.cell><flux:table.cell>{{ \App\Enums\Central\Billing\DeliveryChannelEnum::tryFrom((int) $delivery->channel)?->label() ?? 'Canal inconnu' }}</flux:table.cell><flux:table.cell><flux:badge size="sm">{{ \App\Enums\Central\Billing\DeliveryStatusEnum::tryFrom((int) $delivery->delivery_status)?->label() ?? 'État inconnu' }}</flux:badge></flux:table.cell><flux:table.cell>{{ $delivery->attempts_count }}</flux:table.cell><flux:table.cell class="whitespace-nowrap">{{ $delivery->created_at }}</flux:table.cell><flux:table.cell class="whitespace-nowrap">{{ $delivery->sent_at ?? '—' }}</flux:table.cell></flux:table.row>
                    @empty <flux:table.row><flux:table.cell colspan="6"><div class="aydra-users-empty"><p class="aydra-heading font-medium">Aucun envoi enregistré.</p></div></flux:table.cell></flux:table.row> @endforelse
                </flux:table.rows></flux:table>
                @if ($deliveries->hasPages())<nav aria-label="Pagination des envois" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700"><span class="aydra-muted text-sm">Page {{ $deliveries->currentPage() }} sur {{ $deliveries->lastPage() }}</span><div class="flex gap-2">@if ($deliveries->onFirstPage())<flux:button disabled icon="chevron-left">Précédent</flux:button>@else<flux:button :href="$deliveries->previousPageUrl()" icon="chevron-left">Précédent</flux:button>@endif @if ($deliveries->hasMorePages())<flux:button :href="$deliveries->nextPageUrl()" icon:trailing="chevron-right">Suivant</flux:button>@else<flux:button disabled icon:trailing="chevron-right">Suivant</flux:button>@endif</div></nav>@endif
                </div>
            </section>
        @endif
    </section>
</x-layouts::app>
