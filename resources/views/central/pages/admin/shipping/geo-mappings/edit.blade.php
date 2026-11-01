<x-layouts::app title="Modifier une correspondance">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl">Modifier la correspondance — {{ $mapping->external_name }}</flux:heading>
        @include('central.pages.admin.shipping.geo-mappings.form')
    </section>
</x-layouts::app>
