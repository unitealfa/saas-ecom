<x-layouts::app title="Ajouter un transporteur">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Ajouter un transporteur</h1><p class="aydra-muted text-sm sm:text-base">Ajoutez un transporteur au catalogue commun.</p></div>@can('viewAny', \App\Models\Central\Shipping\ShippingCarrier::class)<flux:button :href="route('central.carriers.index')" icon="arrow-left">Retour aux transporteurs</flux:button>@endcan</header>
        @include('central.pages.admin.shipping.carriers.form')
    </section>
</x-layouts::app>
