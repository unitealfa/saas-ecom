<?php

use App\Services\Central\Dashboard\Dashboard;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /** @return array{shops: \Illuminate\Support\Collection<int, string>, staff: bool} */
    #[Computed]
    public function overview(): array
    {
        return app(Dashboard::class)->forUser(Auth::guard('central')->user());
    }
}; ?>

<section class="flex flex-col gap-6">
    <flux:heading size="xl" level="1">{{ __('Bonjour, :name', ['name' => auth('central')->user()->name]) }}</flux:heading>

    @if (session('status'))
        <flux:callout>{{ session('status') }}</flux:callout>
    @endif
    @if (session('warning'))
        <flux:callout variant="warning">{{ session('warning') }}</flux:callout>
    @endif

    @if ($this->overview['staff'])
        <flux:text>{{ __('Bienvenue dans l’administration Aydra. Les fonctionnalités disponibles dépendent de vos autorisations.') }}</flux:text>
        @can('viewAny', \App\Models\User::class)
            <div>
                <flux:button :href="route('central.users.index')" wire:navigate>{{ __('Consulter les utilisateurs') }}</flux:button>
            </div>
        @endcan
    @else
        <flux:callout>
            <flux:callout.heading>{{ __('La gestion des boutiques est en préparation.') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Votre compte est actif. La création et la gestion des boutiques ne sont pas encore disponibles depuis cet espace.') }}</flux:callout.text>
        </flux:callout>
        @if ($this->overview['shops']->isNotEmpty())
            <div class="space-y-3">
                <flux:heading level="2">{{ __('Vos boutiques') }}</flux:heading>
                <ul class="space-y-2">
                    @foreach ($this->overview['shops'] as $index => $shopName)
                        <li wire:key="shop-name-{{ $index }}"><flux:text>{{ $shopName }}</flux:text></li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</section>
