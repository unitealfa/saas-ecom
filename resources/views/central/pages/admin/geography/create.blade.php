<x-layouts::app :title="__('Ajouter une zone géographique')">
    <section class="mx-auto flex max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Ajouter une wilaya ou une commune</flux:heading>
        <flux:text>La zone sera créée inactive. Son activation constitue une action séparée.</flux:text>
        @include('central.pages.admin.geography.form')
    </section>
</x-layouts::app>
