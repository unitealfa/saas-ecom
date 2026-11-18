<x-layouts::app :title="__('Activité')">
    <section class="mx-auto flex w-full max-w-screen-xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5">
            <div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Activité centrale</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">Consultez les informations enregistrées pour cette activité.</p></div>
            <flux:button :href="route('central.activity.index')" icon="arrow-left">Retour au journal</flux:button>
        </header>
        <section class="aydra-users-panel p-5 sm:p-6" aria-labelledby="activity-description">
            <h2 id="activity-description" class="aydra-heading text-lg font-semibold">Description</h2>
            <p class="aydra-muted mt-3 whitespace-pre-line break-words text-sm">{{ $activity->description ?: 'Non renseignée' }}</p>
        </section>
        <section class="aydra-users-panel p-5 sm:p-6" aria-labelledby="activity-information">
            <div class="mb-6"><h2 id="activity-information" class="aydra-heading text-lg font-semibold">Informations de l’activité</h2><p class="aydra-muted mt-1 text-sm">Identifiants, origine et date de l’événement.</p></div>
            <dl class="aydra-detail-grid grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach (['UUID' => $activity->uuid, 'Date (UTC)' => $activity->created_at?->utc()->format('d/m/Y H:i:s'), 'Journal' => $activity->log_name, 'Événement' => $activity->event, 'Origine' => $activity->origin?->name, 'Acteur UUID' => $actorUuid, 'Sujet UUID' => $subjectUuid, 'Corrélation UUID' => $activity->correlation_id] as $label => $value)
                    <div class="min-w-0"><dt class="font-medium">{{ $label }}</dt><dd class="break-words"><flux:text>{{ $value ?? 'Non renseigné' }}</flux:text></dd></div>
                @endforeach
            </dl>
        </section>
    </section>
</x-layouts::app>
