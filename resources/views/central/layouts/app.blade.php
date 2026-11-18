<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main :inset="app(\App\Services\Central\Authorization\CentralUserAccess::class)->isStaff(auth('central')->user())">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
