<x-layouts::app :title="__('Créer une fonctionnalité')">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Créer une fonctionnalité commerciale</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">La fonctionnalité sera créée inactive. Les limites chiffrées se définissent dans les offres.</p></div>@can('viewAny', \App\Models\Central\Features\Feature::class)<flux:button :href="route('central.features.index')" icon="arrow-left">Retour au catalogue</flux:button>@endcan</header>
        @include('central.pages.admin.features.form')
    </section>
</x-layouts::app>
