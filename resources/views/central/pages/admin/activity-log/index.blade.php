<x-layouts::app :title="__('Journal d’activité')">
    <section class="mx-auto flex w-full max-w-screen-2xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5">
            <div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Journal d’activité central</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">Recherchez les activités centrales et consultez leurs détails.</p></div>
            @can('export', \App\Models\Central\Audit\Activity::class)
                <flux:button :href="route('central.activity.export', request()->only(['log_name', 'event', 'from', 'to', 'actor_uuid', 'subject_uuid', 'correlation_id']))" icon="arrow-down-tray">Exporter les résultats CSV</flux:button>
            @endcan
        </header>
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif

        <section class="aydra-users-panel aydra-activity-filters p-5 sm:p-6" aria-labelledby="activity-filters">
            <h2 id="activity-filters" class="aydra-heading text-lg font-semibold">Rechercher une activité</h2>
            <p class="aydra-muted mt-1 text-sm">Filtrez le journal par événement ou par période. Les suggestions de journal et d’événement proviennent des 500 activités les plus récentes.</p>
            <form method="GET" action="{{ route('central.activity.index') }}" class="mt-5 space-y-4">
              <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:input name="log_name" label="Journal" :value="request('log_name')" list="activity-log-suggestions" />
                <flux:input name="event" label="Événement" :value="request('event')" list="activity-event-suggestions" />
                <div x-data="activityDateRange" data-from="{{ request('from') }}" data-to="{{ request('to') }}" class="relative" @keydown.escape.window="open = false" @click.outside="open = false">
                    <label class="mb-2 block text-sm font-medium">Période (UTC)</label>
                    <flux:button type="button" icon="calendar-days" class="w-full justify-start" @click="open = !open" x-bind:aria-expanded="open" aria-controls="activity-calendar"><span x-text="label">Toutes les dates (UTC)</span></flux:button>
                    <div id="activity-calendar" x-cloak x-show="open" class="absolute right-0 z-30 mt-2 w-80 max-w-[calc(100vw-3rem)] rounded-xl border border-zinc-200 bg-white p-4 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="mb-4 flex flex-wrap gap-2">
                            <flux:button type="button" size="sm" @click="preset(1)">Aujourd’hui</flux:button><flux:button type="button" size="sm" @click="preset(7)">7 jours</flux:button><flux:button type="button" size="sm" @click="preset(30)">30 jours</flux:button>
                        </div>
                        <div class="mb-3 flex items-center justify-between"><button type="button" @click="moveMonth(-1)" aria-label="Mois précédent" class="rounded px-2 py-1 hover:bg-zinc-100 dark:hover:bg-zinc-800">‹</button><span class="text-sm font-semibold capitalize" x-text="title"></span><button type="button" @click="moveMonth(1)" aria-label="Mois suivant" class="rounded px-2 py-1 hover:bg-zinc-100 dark:hover:bg-zinc-800">›</button></div>
                        <div class="grid grid-cols-7 text-center text-xs text-zinc-500"><span>L</span><span>M</span><span>M</span><span>J</span><span>V</span><span>S</span><span>D</span></div>
                        <div class="mt-1 grid grid-cols-7 gap-y-1 text-center">
                            <template x-for="(day, index) in days" :key="index"><div><template x-if="day"><button type="button" class="h-9 w-9 rounded-lg text-sm hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-offset-2 dark:hover:bg-zinc-800" :class="day.date === from || day.date === to ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : (from && to && day.date > from && day.date < to ? 'bg-zinc-100 dark:bg-zinc-800' : '')" :aria-label="day.date" :aria-pressed="day.date === from || day.date === to" @click="select(day.date)" x-text="day.day"></button></template></div></template>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <flux:input name="from" type="date" label="Depuis (UTC)" x-model="from" />
                            <flux:input name="to" type="date" label="Jusqu’au (UTC)" x-model="to" />
                        </div>
                        <div class="mt-3 flex justify-between"><flux:button type="button" size="sm" @click="from = ''; to = ''">Effacer</flux:button><flux:button type="button" size="sm" @click="open = false">Terminé</flux:button></div>
                    </div>
                </div>
              </div>
              <details @if(request()->filled('correlation_id') || request()->filled('actor_uuid') || request()->filled('subject_uuid')) open @endif class="rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <summary class="cursor-pointer text-sm font-medium">Filtres avancés</summary>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <flux:input name="correlation_id" label="UUID de corrélation" :value="request('correlation_id')" list="activity-correlation-suggestions" />
                    <flux:input name="actor_uuid" label="UUID de l’acteur" :value="request('actor_uuid')" list="activity-actor-suggestions" />
                    <flux:input name="subject_uuid" label="UUID du sujet central" :value="request('subject_uuid')" list="activity-subject-suggestions" />
                </div>
              </details>
                @foreach (['log_name' => 'log', 'event' => 'event', 'correlation_id' => 'correlation', 'actor_uuid' => 'actor', 'subject_uuid' => 'subject'] as $field => $suggestionId)
                    <datalist id="activity-{{ $suggestionId }}-suggestions">
                        @foreach ($suggestions[$field] ?? $activities->getCollection()->pluck($field)->filter()->unique() as $suggestion)<option value="{{ $suggestion }}"></option>@endforeach
                    </datalist>
                @endforeach
                <div class="flex flex-wrap items-end gap-2">
                    <flux:button type="submit" variant="primary" icon="magnifying-glass">Rechercher</flux:button>
                    <flux:button :href="route('central.activity.index')">Effacer</flux:button>
                </div>
            </form>
            @php($activeFilters = array_filter(request()->only(['log_name', 'event', 'from', 'to', 'correlation_id', 'actor_uuid', 'subject_uuid']), fn ($value) => is_string($value) && $value !== ''))
            @if ($activeFilters)
                <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <span class="aydra-muted text-sm">Filtres actifs :</span>
                    @foreach (['log_name' => 'Journal', 'event' => 'Événement', 'from' => 'Depuis (UTC)', 'to' => 'Jusqu’au (UTC)', 'correlation_id' => 'Corrélation', 'actor_uuid' => 'Acteur', 'subject_uuid' => 'Sujet'] as $field => $label)
                        @if (isset($activeFilters[$field]))<a class="rounded-full bg-zinc-100 px-3 py-1 text-sm hover:bg-zinc-200 dark:bg-zinc-800 dark:hover:bg-zinc-700" href="{{ route('central.activity.index', array_diff_key($activeFilters, [$field => true])) }}" aria-label="Retirer le filtre {{ $label }}">{{ $label }} : {{ $activeFilters[$field] }} &times;</a>@endif
                    @endforeach
                </div>
            @endif
        </section>

        <div class="aydra-users-panel">
            <div class="aydra-users-toolbar"><div><h2 class="aydra-heading text-lg font-semibold">Activités</h2><p class="aydra-muted mt-1 text-sm">{{ $activities->total() }} {{ $activities->total() === 1 ? 'activité trouvée' : 'activités trouvées' }}</p></div></div>
            <div class="aydra-users-table">
                <flux:table class="whitespace-normal">
                    <flux:table.columns><flux:table.column>Date (UTC)</flux:table.column><flux:table.column>Journal</flux:table.column><flux:table.column>Événement</flux:table.column><flux:table.column>Acteur UUID</flux:table.column><flux:table.column>Sujet UUID</flux:table.column><flux:table.column>Actions</flux:table.column></flux:table.columns>
                    <flux:table.rows>
                        @forelse ($activities as $activity)
                            <flux:table.row :key="$activity->uuid">
                                <flux:table.cell variant="strong" class="whitespace-nowrap">{{ $activity->created_at?->utc()->format('d/m/Y H:i:s') }}</flux:table.cell>
                                <flux:table.cell>{{ $activity->log_name ?? '—' }}</flux:table.cell><flux:table.cell>{{ $activity->event ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $activity->actor_uuid ?? 'Système / indisponible' }}</flux:table.cell><flux:table.cell>{{ $activity->subject_uuid ?? '—' }}</flux:table.cell>
                                <flux:table.cell><flux:button :href="route('central.activity.show', $activity)">Détails</flux:button></flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="6"><div class="aydra-users-empty"><p class="aydra-heading font-medium">Aucune activité trouvée.</p><p class="aydra-muted mt-1 text-sm">Essayez d’autres critères de recherche.</p></div></flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
                @if ($activities->hasPages())
                    <nav aria-label="Pagination des activités" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                        <span class="aydra-muted text-sm">Page {{ $activities->currentPage() }} sur {{ $activities->lastPage() }}</span>
                        <div class="flex gap-2">
                            @if ($activities->onFirstPage())<flux:button disabled icon="chevron-left">Précédent</flux:button>@else<flux:button :href="$activities->previousPageUrl()" icon="chevron-left">Précédent</flux:button>@endif
                            @if ($activities->hasMorePages())<flux:button :href="$activities->nextPageUrl()" icon:trailing="chevron-right">Suivant</flux:button>@else<flux:button disabled icon:trailing="chevron-right">Suivant</flux:button>@endif
                        </div>
                    </nav>
                @endif
            </div>
        </div>
    </section>
</x-layouts::app>
