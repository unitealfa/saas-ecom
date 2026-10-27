<x-layouts::app :title="__('Modifier une fonctionnalité')">
    <section class="mx-auto flex max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Modifier {{ $feature->name }}</flux:heading>
        <flux:text>Le code est permanent. Les caractéristiques d’une fonctionnalité utilisée restent protégées.</flux:text>
        @include('central.pages.admin.features.form')
    </section>
</x-layouts::app>
