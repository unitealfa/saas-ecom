<x-layouts::app :title="__('Modifier une zone géographique')">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Modifier {{ $area->name_fr }}</h1><p class="aydra-muted max-w-2xl text-sm sm:text-base">Le pays, le type, le rattachement et le code sont permanents. La modification conserve l’état actuel de la zone.</p></div>@can('view', $area)<flux:button :href="route('central.geography.show', $area->uuid)" icon="arrow-left">Retour à la zone</flux:button>@endcan</header>
        @include('central.pages.admin.geography.form')
    </section>
</x-layouts::app>
