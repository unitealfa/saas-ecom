<x-layouts::app :title="__('Activité')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl">Activité centrale</flux:heading>
        <dl class="grid gap-4 sm:grid-cols-2">
            @foreach (['UUID' => $activity->uuid, 'Date (UTC)' => $activity->created_at?->utc()->format('d/m/Y H:i:s'), 'Journal' => $activity->log_name, 'Événement' => $activity->event, 'Origine' => $activity->origin?->name, 'Acteur UUID' => $actorUuid, 'Sujet UUID' => $subjectUuid, 'Corrélation UUID' => $activity->correlation_id] as $label => $value)
                <div><dt class="font-medium">{{ $label }}</dt><dd>{{ $value ?? 'Non renseigné' }}</dd></div>
            @endforeach
        </dl>
        <flux:button :href="route('central.activity.index')">Retour au journal</flux:button>
    </section>
</x-layouts::app>
