<x-layouts::app title="Détail d'un envoi SaaS">
    <section class="mx-auto flex w-full max-w-4xl flex-col gap-6">
        <flux:heading size="xl" level="1">Envoi {{ $delivery->uuid }}</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach

        <dl class="grid gap-4 sm:grid-cols-2">
            <div><dt>Document</dt><dd>{{ $delivery->document?->number ?? 'Inconnu' }}</dd></div>
            <div><dt>Type</dt><dd>{{ $delivery->document?->document_type?->label() ?? 'Inconnu' }}</dd></div>
            <div><dt>Commerçant</dt><dd>{{ $delivery->owner?->name ?? 'Compte archivé' }}</dd></div>
            <div><dt>Canal</dt><dd>{{ $delivery->channel->label() }}</dd></div>
            <div><dt>État</dt><dd>{{ $delivery->delivery_status->label() }}</dd></div>
            <div><dt>Tentatives</dt><dd>{{ $delivery->attempts_count }}</dd></div>
            <div><dt>Accepté par le transport</dt><dd>{{ $delivery->sent_at?->format('d/m/Y H:i') ?? 'Non confirmé' }}</dd></div>
            <div><dt>Remis</dt><dd>{{ $delivery->delivered_at?->format('d/m/Y H:i') ?? 'Non confirmé' }}</dd></div>
            <div><dt>Erreur technique</dt><dd>{{ $delivery->error_code ?? 'Aucune' }}</dd></div>
            <div><dt>Identifiant de corrélation</dt><dd>{{ $delivery->correlation_id }}</dd></div>
        </dl>

        <flux:callout>
            Le destinataire est chiffré dans la base et n'est pas affiché ici. Un e-mail accepté par le serveur SMTP
            n'est pas une preuve de réception. Un résultat incertain bloque les relances automatiques.
        </flux:callout>

        @can('retry', $delivery)
            <form method="POST" action="{{ route('central.deliveries.retry', $delivery) }}"
                  x-on:submit="if (! window.confirm('Relancer cet envoi échoué ?')) $event.preventDefault()">
                @csrf
                <input type="hidden" name="expected_state" value="{{ $delivery->managementState() }}">
                <flux:button type="submit" variant="primary">Relancer l'envoi échoué</flux:button>
            </form>
        @endcan

        <flux:heading>Historique</flux:heading>
        @forelse ($history as $event)
            <flux:text>{{ $event->created_at->format('d/m/Y H:i') }} — {{ $event->description }}</flux:text>
        @empty
            <flux:text>Pas encore d'événement d'audit.</flux:text>
        @endforelse
        {{ $history->links() }}
        <flux:button :href="route('central.deliveries.index')">Retour aux envois</flux:button>
    </section>
</x-layouts::app>
