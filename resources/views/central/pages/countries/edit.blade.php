<x-layouts::app :title="__('Modifier un pays')">
    <section class="mx-auto flex max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Modifier {{ $country->name_fr }}</flux:heading>
        @include('central.pages.countries.form')
    </section>
</x-layouts::app>
