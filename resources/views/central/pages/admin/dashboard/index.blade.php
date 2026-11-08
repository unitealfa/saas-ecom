<x-layouts::app :title="__('Tableau de bord administratif')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Tableau de bord administratif</flux:heading>
        @if ($overview !== null)
            <flux:text>Comptes centraux non archivés sans rôle SaaS, boutiques non archivées et états d’abonnement enregistrés.</flux:text>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border p-5">
                    <flux:heading level="2">Comptes commerçants</flux:heading>
                    <flux:text>Total : {{ array_sum($overview['merchants']) }}</flux:text>
                    @foreach (\App\Enums\Central\Users\StatusEnum::cases() as $status)
                        <flux:text>{{ $status->label() }} : {{ $overview['merchants'][$status->value] ?? 0 }}</flux:text>
                    @endforeach
                </div>
                <div class="rounded-xl border p-5">
                    <flux:heading level="2">Boutiques</flux:heading>
                    <flux:text>Total : {{ array_sum($overview['shops']) }}</flux:text>
                    @foreach (\App\Enums\Central\Tenants\StatusEnum::cases() as $status)
                        <flux:text>{{ $status->label() }} : {{ $overview['shops'][$status->value] ?? 0 }}</flux:text>
                    @endforeach
                </div>
                <div class="rounded-xl border p-5">
                    <flux:heading level="2">Abonnements</flux:heading>
                    <flux:text>Total : {{ array_sum($overview['subscriptions']) }}</flux:text>
                    @foreach (\App\Enums\Central\Subscriptions\StatusEnum::cases() as $status)
                        <flux:text>{{ $status->label() }} : {{ $overview['subscriptions'][$status->value] ?? 0 }}</flux:text>
                    @endforeach
                    <flux:text>États enregistrés : les échéances peuvent encore être à résoudre.</flux:text>
                </div>
            </div>
        @endif
        @if ($payments !== null)
            <div class="rounded-xl border p-5">
                <flux:heading level="2">Paiements vérifiés</flux:heading>
                <flux:text>Montants bruts enregistrés, toutes dates confondues, hors remboursements et corrections ; ne représentent pas le revenu net.</flux:text>
                @forelse ($payments as $payment)
                    <flux:text>{{ $payment['total'] }} {{ $payment['currency'] }} — {{ $payment['count'] }} paiement(s)</flux:text>
                @empty
                    <flux:text>Aucun paiement vérifié enregistré.</flux:text>
                @endforelse
            </div>
        @endif
    </section>
</x-layouts::app>
