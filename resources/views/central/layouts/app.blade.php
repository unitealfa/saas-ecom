<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main :inset="app(\App\Services\Central\Authorization\CentralUserAccess::class)->isStaff(auth('central')->user())">
        <x-central::components.admin-navigation />
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
