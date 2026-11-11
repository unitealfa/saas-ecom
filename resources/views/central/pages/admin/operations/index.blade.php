<x-layouts::app :title="__('Exploitation centrale')">
    <section class="flex flex-col gap-6">
        <div>
            <flux:heading size="xl" level="1">Exploitation centrale</flux:heading>
            <flux:text>Suivi en lecture seule des opérations centrales enregistrées.</flux:text>
        </div>

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
    </section>
</x-layouts::app>
