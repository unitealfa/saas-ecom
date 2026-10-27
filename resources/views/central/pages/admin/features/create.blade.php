<x-layouts::app :title="__('Créer une fonctionnalité')">
    <section class="mx-auto flex max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Créer une fonctionnalité commerciale</flux:heading>
        <flux:text>La fonctionnalité sera créée inactive. Les limites chiffrées se définissent dans les offres.</flux:text>
        @include('central.pages.admin.features.form')
    </section>
</x-layouts::app>
