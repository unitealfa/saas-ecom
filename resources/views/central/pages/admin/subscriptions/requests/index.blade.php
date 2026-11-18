<x-layouts::app :title="$ownerMode ? 'Mes demandes d’abonnement' : 'Demandes d’abonnement'">
    <section class="mx-auto flex w-full max-w-screen-2xl flex-col gap-6">
        <header class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">{{ $ownerMode ? 'Mes demandes d’abonnement' : 'Abonnements — Demandes' }}</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">Consultez les demandes et leur état de traitement.</p></header>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @include('central.pages.admin.subscriptions.requests.list')
    </section>
</x-layouts::app>
