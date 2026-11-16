<x-layouts::app :title="$ownerMode ? 'Mes demandes d’abonnement' : 'Demandes d’abonnement'">
    <section class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $ownerMode ? 'Mes demandes d’abonnement' : 'Abonnements — Demandes' }}</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @include('central.pages.admin.subscriptions.requests.list')
    </section>
</x-layouts::app>
