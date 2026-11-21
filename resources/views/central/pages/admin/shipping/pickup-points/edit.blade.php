<x-layouts::app title="Modifier le bureau de retrait">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Modifier le bureau de retrait</h1><p class="aydra-muted text-sm sm:text-base">Mettez à jour les informations publiques du bureau.</p></header>
        @include('central.pages.admin.shipping.pickup-points.form')
    </section>
</x-layouts::app>
