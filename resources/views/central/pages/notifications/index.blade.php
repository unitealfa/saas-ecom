<x-layouts::app title="Notifications">
    <section class="mx-auto flex max-w-3xl flex-col gap-5">
        <flux:heading size="xl" level="1">Notifications</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @forelse ($notifications as $notification)
            <article class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text>{{ $notification->created_at->format('d/m/Y H:i') }} — {{ $notification->read_at ? 'Lue' : 'Non lue' }}</flux:text>
                <p>{{ $notification->data['message'] ?? '' }}</p>
                @if ($notification->data['url'] ?? null)<flux:button :href="$notification->data['url']">Consulter le dossier</flux:button>@endif
                @unless ($notification->read_at)
                    <form method="POST" action="{{ route('notifications.read', $notification) }}">@csrf @method('PATCH')
                        <flux:button type="submit">Marquer comme lue</flux:button>
                    </form>
                @endunless
            </article>
        @empty<flux:text>Aucune notification.</flux:text>@endforelse
        {{ $notifications->links() }}
    </section>
</x-layouts::app>
