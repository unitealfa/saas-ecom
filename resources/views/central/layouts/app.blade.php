<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        <x-central::components.admin-navigation />
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
