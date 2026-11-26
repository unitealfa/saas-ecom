<?php

use App\Services\Central\Dashboard\Dashboard;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /** @return array{shops: \Illuminate\Support\Collection, staff: bool} */
    #[Computed]
    public function overview(): array
    {
        return app(Dashboard::class)->forUser(Auth::guard('central')->user());
    }
}; ?>

<section class="mx-auto flex w-full max-w-screen-2xl flex-col gap-6">
    <header class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">{{ __('Bonjour, :name', ['name' => auth('central')->user()->name]) }}</h1><p class="aydra-muted text-sm">Retrouvez votre espace et les informations de vos boutiques.</p></header>

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
        @can('createOwned', [\App\Models\Central\Tenants\Tenant::class, auth('central')->user()])
            <div><flux:button variant="primary" icon="plus" :href="route('owner.shops.create')">Créer une boutique</flux:button></div>
        @endcan
        <flux:callout>
            <flux:callout.heading>{{ __('Bienvenue dans votre espace commerçant.') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Votre compte est actif. Retrouvez vos boutiques ci-dessous et ouvrez leur administration.') }}</flux:callout.text>
        </flux:callout>
        @if ($this->overview['shops']->isNotEmpty())
            <div id="mes-boutiques" class="aydra-users-panel" wire:poll.15s.visible>
                <div class="aydra-users-toolbar"><h2 class="aydra-heading text-lg font-semibold">{{ __('Vos boutiques') }}</h2></div>
                <ul class="divide-y divide-zinc-200 px-5 dark:divide-zinc-700">
                    @foreach ($this->overview['shops'] as $shop)
                        <li wire:key="shop-name-{{ $shop['uuid'] }}" class="flex flex-wrap items-center justify-between gap-3 py-4">
                            <div><p class="aydra-heading font-semibold">{{ $shop['name'] }}</p><flux:text>{{ $shop['status'] }}</flux:text></div>
                            @if ($shop['ready'])
                                <div class="flex flex-wrap gap-2"><flux:button :href="route('owner.shops.access', $shop['uuid'])">Administrer</flux:button>
                                <flux:button :href="route('owner.shops.domains.index', $shop['uuid'])">Domaines</flux:button></div>
                            @else
                                <flux:text>Accès indisponible tant que la boutique n’est pas prête et active.</flux:text>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            <div id="mes-boutiques" class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading text-lg font-semibold">Vos boutiques</h2><p class="aydra-muted mt-2 text-sm">Aucune boutique enregistrée pour le moment.</p></div>
        @endif
    @endif
</section>
