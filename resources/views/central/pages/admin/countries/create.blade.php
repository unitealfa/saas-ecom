<x-layouts::app :title="__('Créer un pays')">
    <section class="mx-auto flex max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Créer un pays</flux:heading>
        @include('central.pages.admin.countries.form')
    </section>
</x-layouts::app>
