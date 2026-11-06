<x-layouts::app :title="__('Quotas')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl">Quotas — {{ $owner->name }}</flux:heading>
        @if (session('status'))<flux:text role="status">{{ session('status') }}</flux:text>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        @if ($pending)
            <flux:text>Abonnement à résoudre : les droits actuels doivent être recalculés avant d’afficher le plafond applicable.</flux:text>
        @else
            <flux:text>Offre enregistrée : {{ $subscription->plan?->name ?? 'Indisponible' }}</flux:text>
            <flux:text>Plafond de boutiques : {{ $grant === null || ! $grant->is_active ? 'Non disponible' : ($grant->limit ?? 'Sans limite') }}</flux:text>
            @if ($grant !== null && $grant->is_active && $grant->limit !== null)
                <flux:text>Places de création disponibles : {{ max(0, $grant->limit - $shops->whereNull('deleted_at')->count()) }}</flux:text>
            @endif
        @endif
        <flux:text>Boutiques existantes : {{ $shops->whereNull('deleted_at')->count() }}. Boutiques archivées : {{ $shops->whereNotNull('deleted_at')->count() }}.</flux:text>
        <flux:text>Les boutiques archivées ne consomment plus de place ; une suspension ou désactivation conserve sa place.</flux:text>
        <ul>@foreach ($shops as $shop)<li>{{ $shop->shop_name }} — {{ $shop->status->label() }}</li>@endforeach</ul>
        <flux:heading level="2">Fonctionnalités de l’offre enregistrée</flux:heading>
        @forelse ($features as $feature)
            <flux:text>{{ $feature->feature?->name ?? 'Fonctionnalité indisponible' }} — {{ $feature->is_active ? 'Active' : 'Inactive' }} ; plafond : {{ $feature->limit ?? 'Sans limite' }}</flux:text>
        @empty <flux:text>Aucune composition disponible pour le moment.</flux:text> @endforelse
        <flux:heading level="2">Consommations enregistrées</flux:heading>
        @forelse ($usage as $entry)
            <flux:text>{{ $entry->name }} ({{ $entry->code }}) — {{ $entry->shop_name ?? 'Propriétaire' }} : {{ $entry->quantity }} ; {{ $entry->period_starts_at }} — {{ $entry->period_ends_at ?? 'sans fin' }}</flux:text>
        @empty <flux:text>Aucun compteur enregistré.</flux:text> @endforelse
        {{ $usage->links() }}
        <div class="flex gap-3">
            @can('reconcileQuota', $owner)
                <form method="POST" action="{{ route('central.quotas.reconcile', $owner) }}">@csrf<input type="hidden" name="expected_state" value="{{ $state }}"><flux:button type="submit">Recalculer les quotas</flux:button></form>
            @endcan
            @can('viewQuotaHistory', $owner)<flux:button :href="route('central.quotas.history', $owner)">Historique</flux:button>@endcan
            <flux:button :href="route('central.quotas.index')">Retour</flux:button>
        </div>
    </section>
</x-layouts::app>
