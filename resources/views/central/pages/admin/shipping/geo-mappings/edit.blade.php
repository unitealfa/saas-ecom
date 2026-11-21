<x-layouts::app title="Modifier une correspondance">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Modifier la correspondance</h1><p class="aydra-muted text-sm sm:text-base">{{ $mapping->external_name }}</p></header>
        @include('central.pages.admin.shipping.geo-mappings.form')
    </section>
</x-layouts::app>
