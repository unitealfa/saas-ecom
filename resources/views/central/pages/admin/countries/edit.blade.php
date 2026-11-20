<x-layouts::app :title="__('Modifier un pays')">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Modifier {{ $country->name_fr }}</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">Mettez à jour les informations du pays.</p></div>@can('viewAny', \App\Models\Central\Countries\Country::class)<flux:button :href="route('central.countries.index')" icon="arrow-left">Retour aux pays</flux:button>@endcan</header>
        @include('central.pages.admin.countries.form')
    </section>
</x-layouts::app>
