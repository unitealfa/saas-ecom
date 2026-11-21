<x-layouts::app title="Notifications">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="space-y-2">
            <h1 class="aydra-heading text-3xl font-semibold tracking-tight text-zinc-900 sm:text-4xl dark:text-white">Notifications</h1>
            <p class="aydra-muted max-w-2xl text-sm text-zinc-600 sm:text-base dark:text-zinc-400">Retrouvez les actualités et les actions liées à votre compte.</p>
        </header>

        @if (session('status'))<flux:callout role="status">{{ session('status') }}</flux:callout>@endif

        <section class="aydra-users-panel overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900" aria-labelledby="notification-list">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 px-5 py-5 sm:px-6 dark:border-zinc-700">
                <div><h2 id="notification-list" class="aydra-heading text-lg font-semibold text-zinc-900 dark:text-white">Votre activité</h2><p class="aydra-muted mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $notifications->total() }} {{ $notifications->total() === 1 ? 'notification' : 'notifications' }}</p></div>
            </div>
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @forelse ($notifications as $notification)
                    <article class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-start sm:gap-4 sm:px-6">
                        <div class="flex min-w-0 flex-1 items-start gap-3">
                            <div @class(['mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl', 'bg-blue-50 text-blue-700 dark:bg-blue-400/10 dark:text-blue-300' => ! $notification->read_at, 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' => $notification->read_at])><flux:icon.bell class="size-5" /></div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2"><flux:badge size="sm" :color="$notification->read_at ? 'zinc' : 'blue'">{{ $notification->read_at ? 'Lue' : 'Non lue' }}</flux:badge><time class="aydra-muted text-xs text-zinc-500 dark:text-zinc-400" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->format('d/m/Y H:i') }}</time></div>
                                <p class="aydra-heading mt-3 break-words text-sm leading-6 text-zinc-900 dark:text-zinc-100">{{ $notification->data['message'] ?? '' }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                            @if ($notification->data['url'] ?? null)<flux:button :href="$notification->data['url']" size="sm">Consulter le dossier</flux:button>@endif
                            @unless ($notification->read_at)
                                <form method="POST" action="{{ route('notifications.read', $notification) }}">@csrf @method('PATCH')<flux:button type="submit" size="sm">Marquer comme lue</flux:button></form>
                            @endunless
                        </div>
                    </article>
                @empty
                    <div class="px-5 py-12 text-center sm:px-6"><div class="mx-auto flex size-12 items-center justify-center rounded-xl bg-blue-50 text-blue-700 dark:bg-blue-400/10 dark:text-blue-300"><flux:icon.bell class="size-6" /></div><p class="aydra-heading mt-4 font-medium text-zinc-900 dark:text-white">Aucune notification.</p><p class="aydra-muted mt-1 text-sm text-zinc-600 dark:text-zinc-400">Les informations liées à votre compte apparaîtront ici.</p></div>
                @endforelse
            </div>
            @if ($notifications->hasPages())
                <nav aria-label="Pagination des notifications" class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 px-5 py-4 sm:px-6 dark:border-zinc-700"><span class="aydra-muted text-sm text-zinc-600 dark:text-zinc-400">Page {{ $notifications->currentPage() }} sur {{ $notifications->lastPage() }}</span><div class="flex gap-2">@if ($notifications->onFirstPage())<flux:button disabled icon="chevron-left">Précédent</flux:button>@else<flux:button :href="$notifications->previousPageUrl()" icon="chevron-left">Précédent</flux:button>@endif @if ($notifications->hasMorePages())<flux:button :href="$notifications->nextPageUrl()" icon:trailing="chevron-right">Suivant</flux:button>@else<flux:button disabled icon:trailing="chevron-right">Suivant</flux:button>@endif</div></nav>
            @endif
        </section>
    </section>
</x-layouts::app>
