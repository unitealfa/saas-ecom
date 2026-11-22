<x-layouts::app :title="__('Créer un pays')">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Créer un pays</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">Ajoutez un pays au référentiel ; son activation est une action séparée.</p></div>@can('viewAny', \App\Models\Central\Countries\Country::class)<flux:button :href="route('central.countries.index')" icon="arrow-left">Retour aux pays</flux:button>@endcan</header>
        @include('central.pages.admin.countries.form')
    </section>
</x-layouts::app>
