@props(['name'])

<div {{ $attributes->class('flex flex-col gap-1 px-2 py-3') }}>
    <flux:text size="sm">{{ __('Compte boutique') }}</flux:text>
    <flux:heading class="break-words">{{ $name }}</flux:heading>
</div>
