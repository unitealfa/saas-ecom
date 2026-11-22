<x-layouts::app :title="__('Modifier une fonctionnalité')">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Modifier {{ $feature->name }}</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">Le code est permanent. Les caractéristiques d’une fonctionnalité utilisée restent protégées.</p></div>@can('viewAny', \App\Models\Central\Features\Feature::class)<flux:button :href="route('central.features.index')" icon="arrow-left">Retour au catalogue</flux:button>@endcan</header>
        @include('central.pages.admin.features.form')
    </section>
</x-layouts::app>
