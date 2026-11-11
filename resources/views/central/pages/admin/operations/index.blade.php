<x-layouts::app :title="__('Exploitation centrale')">
    <section class="flex flex-col gap-6">
        <div>
            <flux:heading size="xl" level="1">Exploitation centrale</flux:heading>
            <flux:text>Suivi des opérations centrales et traitements autorisés.</flux:text>
        </div>

        @error('cursor')
            <flux:callout variant="danger">{{ $message }}</flux:callout>
        @enderror

        @php($result = session('operations_result'))
        @if ($result && (($result['type'] === 'subscriptions' && \Illuminate\Support\Facades\Gate::allows('reconcileSubscriptions', \App\Models\User::class)) || ($result['type'] === 'payments' && \Illuminate\Support\Facades\Gate::allows('reconcilePayments', \App\Models\User::class))))
            <flux:callout>
                <flux:callout.heading>Résultat du lot</flux:callout.heading>
                <flux:callout.text>
                    @if ($result['type'] === 'subscriptions')
                        Comptes traités : {{ $result['processed'] }}. Échecs : {{ $result['failed'] }}.
                    @else
                        Paiements examinés : {{ $result['processed'] }}. Anomalies : {{ count($result['anomalies']) }}. Aucun paiement n’a été confirmé ou modifié.
                    @endif
                </flux:callout.text>
                @if ($result['type'] === 'subscriptions')
                    @foreach ($result['failed_owners'] as $ownerUuid)
                        <flux:text>Compte à examiner : {{ $ownerUuid }}</flux:text>
                    @endforeach
                @endif
                @if ($result['type'] === 'payments')
                    @foreach ($result['anomalies'] as $anomaly)
                        <flux:text>{{ $anomaly['uuid'] }} — {{ implode(', ', $anomaly['reasons']) }}</flux:text>
                    @endforeach
                @endif
            </flux:callout>
        @endif

        <div class="flex flex-wrap gap-3">
            @can('reconcileSubscriptions', \App\Models\User::class)
                <form method="POST" action="{{ route('central.operations.subscriptions.reconcile') }}">
                    @csrf
                    <flux:button type="submit">Réconcilier les abonnements</flux:button>
                </form>
                @if (isset($result) && $result['type'] === 'subscriptions' && $result['next_cursor'])
                    <form method="POST" action="{{ route('central.operations.subscriptions.reconcile') }}">
                        @csrf
                        <input type="hidden" name="cursor" value="{{ $result['next_cursor'] }}">
                        <flux:button type="submit">Continuer les abonnements</flux:button>
                    </form>
                @endif
            @endcan
            @can('reconcilePayments', \App\Models\User::class)
                <form method="POST" action="{{ route('central.operations.payments.reconcile') }}">
                    @csrf
                    <flux:button type="submit">Rapprocher les paiements</flux:button>
                </form>
                @if (isset($result) && $result['type'] === 'payments' && $result['next_cursor'])
                    <form method="POST" action="{{ route('central.operations.payments.reconcile') }}">
                        @csrf
                        <input type="hidden" name="cursor" value="{{ $result['next_cursor'] }}">
                        <flux:button type="submit">Continuer les paiements</flux:button>
                    </form>
                @endif
            @endcan
        </div>

        @if ($deployments !== null)
        <div class="space-y-4">
            <flux:heading size="lg" level="2">Déploiements de boutiques</flux:heading>
            @forelse ($deployments as $deployment)
                <div class="rounded-xl border p-4 space-y-1">
                    <flux:text>Référence : {{ $deployment->uuid }}</flux:text>
                    <flux:text>Boutique : {{ $deployment->shop_uuid }}</flux:text>
                    <flux:text>{{ \App\Enums\Central\Deployments\OperationEnum::tryFrom((int) $deployment->operation)?->label() ?? 'Type inconnu' }} — {{ \App\Enums\Central\Deployments\StatusEnum::tryFrom((int) $deployment->status)?->label() ?? 'État inconnu' }}</flux:text>
                    <flux:text>Tentative : {{ $deployment->attempt_number }} · Créé : {{ $deployment->created_at }} · Terminé : {{ $deployment->ended_at ?? '—' }}</flux:text>
                </div>
            @empty
                <flux:text>Aucun déploiement enregistré.</flux:text>
            @endforelse
            {{ $deployments->links() }}
        </div>

        <div class="space-y-4">
            <flux:heading size="lg" level="2">Envois de documents</flux:heading>
            @forelse ($deliveries as $delivery)
                <div class="rounded-xl border p-4 space-y-1">
                    <flux:text>Référence : {{ $delivery->uuid }}</flux:text>
                    <flux:text>{{ \App\Enums\Central\Billing\DeliveryChannelEnum::tryFrom((int) $delivery->channel)?->label() ?? 'Canal inconnu' }} — {{ \App\Enums\Central\Billing\DeliveryStatusEnum::tryFrom((int) $delivery->delivery_status)?->label() ?? 'État inconnu' }}</flux:text>
                    <flux:text>Tentatives : {{ $delivery->attempts_count }} · Créé : {{ $delivery->created_at }} · Accepté : {{ $delivery->sent_at ?? '—' }}</flux:text>
                </div>
            @empty
                <flux:text>Aucun envoi enregistré.</flux:text>
            @endforelse
            {{ $deliveries->links() }}
        </div>
        @endif
    </section>
</x-layouts::app>
