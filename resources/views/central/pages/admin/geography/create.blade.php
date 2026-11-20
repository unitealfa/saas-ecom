<x-layouts::app :title="__('Ajouter une zone géographique')">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Ajouter une wilaya ou une commune</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">La zone sera créée inactive. Son activation constitue une action séparée.</p></div>@can('viewAny', \App\Models\Central\Geography\GeographicArea::class)<flux:button :href="route('central.geography.index')" icon="arrow-left">Retour au référentiel</flux:button>@endcan</header>
        @include('central.pages.admin.geography.form')
    </section>
</x-layouts::app>
