<x-layouts::app :title="__('Modifier une zone géographique')">
    <section class="mx-auto flex max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Modifier {{ $area->name_fr }}</flux:heading>
        <flux:text>Le pays, le type, le rattachement et le code sont permanents. La modification conserve l’état actuel de la zone.</flux:text>
        @include('central.pages.admin.geography.form')
    </section>
</x-layouts::app>
