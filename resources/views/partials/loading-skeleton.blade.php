@php($tableSkeleton = ($variant ?? 'page') === 'table')
<div data-loading-skeleton role="status" aria-live="polite" class="mx-auto w-full max-w-screen-2xl">
    <span class="sr-only">Chargement en cours…</span>
    <flux:skeleton.group animate="shimmer" aria-hidden="true" class="space-y-6">
        @unless ($tableSkeleton)
            <div class="space-y-3">
                <flux:skeleton class="h-8 w-48 max-w-full" />
                <flux:skeleton.line class="w-80 max-w-full" />
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                @for ($i = 0; $i < 3; $i++)
                    <div class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
                        <flux:skeleton.line class="w-24" />
                        <flux:skeleton class="h-8 w-20" />
                    </div>
                @endfor
            </div>
        @endunless
        <div class="space-y-4 rounded-xl border border-zinc-200 p-5 dark:border-zinc-700">
            <flux:skeleton.line class="w-40 max-w-full" />
            @for ($i = 0; $i < 4; $i++)
                <div class="grid grid-cols-3 gap-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:skeleton.line />
                    <flux:skeleton.line class="w-4/5" />
                    <flux:skeleton.line class="w-3/5" />
                </div>
            @endfor
        </div>
    </flux:skeleton.group>
</div>
