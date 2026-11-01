<x-layouts::app :title="'Modifier '.$carrier->name">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl">Modifier {{ $carrier->name }}</flux:heading>
        @include('central.pages.admin.shipping.carriers.form')
    </section>
</x-layouts::app>
