<x-layouts::app :title="__('Journal d’activité')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl">Journal d’activité central</flux:heading>
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <form method="GET" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <flux:input name="log_name" label="Journal" :value="request('log_name')" />
            <flux:input name="event" label="Événement" :value="request('event')" />
            <flux:input name="correlation_id" label="UUID de corrélation" :value="request('correlation_id')" />
            <flux:input name="actor_uuid" label="UUID de l’acteur" :value="request('actor_uuid')" />
            <flux:input name="subject_uuid" label="UUID du sujet central" :value="request('subject_uuid')" />
            <flux:input name="from" type="date" label="Depuis (UTC)" :value="request('from')" />
            <flux:input name="to" type="date" label="Jusqu’au (UTC)" :value="request('to')" />
            <flux:button type="submit">Rechercher</flux:button>
        </form>
        @can('export', \App\Models\Central\Audit\Activity::class)
            <flux:button :href="route('central.activity.export', request()->only(['log_name', 'event', 'from', 'to', 'actor_uuid', 'subject_uuid', 'correlation_id']))">Exporter les résultats CSV</flux:button>
        @endcan
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th>Date (UTC)</th><th>Journal</th><th>Événement</th><th>Acteur UUID</th><th>Sujet UUID</th><th>Actions</th></tr></thead><tbody>
            @forelse ($activities as $activity)
                <tr><td class="p-3">{{ $activity->created_at?->utc()->format('d/m/Y H:i:s') }}</td><td>{{ $activity->log_name ?? '—' }}</td><td>{{ $activity->event ?? '—' }}</td><td>{{ $activity->actor_uuid ?? 'Système / indisponible' }}</td><td>{{ $activity->subject_uuid ?? '—' }}</td><td><flux:button :href="route('central.activity.show', $activity)">Détails</flux:button></td></tr>
            @empty <tr><td colspan="6">Aucune activité trouvée.</td></tr> @endforelse
        </tbody></table></div>
        {{ $activities->links() }}
    </section>
</x-layouts::app>
