@props(['shopName' => null, 'loginAction' => null, 'passwordRequestUrl' => null])

<x-tenant::layouts.auth :title="__('Connexion à la boutique')" :shop-name="$shopName">
    <x-auth-header :title="__('Connexion à la boutique')" :description="__('Connectez-vous avec votre compte de cette boutique.')" />
    <x-auth-session-status class="text-center" :status="session('status')" />

    @if ($loginAction !== null)
        <form method="POST" action="{{ $loginAction }}" class="flex flex-col gap-6">
            @csrf

            <flux:input
                name="email"
                :label="__('Adresse e-mail')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="username"
            />

            <flux:input
                name="password"
                :label="__('Mot de passe')"
                type="password"
                required
                autocomplete="current-password"
                viewable
            />

            <flux:checkbox name="remember" :label="__('Se souvenir de moi')" :checked="old('remember')" />

            @if ($passwordRequestUrl !== null)
                <flux:link :href="$passwordRequestUrl">{{ __('Mot de passe oublié ?') }}</flux:link>
            @endif

            <flux:button variant="primary" type="submit" class="w-full">{{ __('Se connecter') }}</flux:button>
        </form>
    @else
        @include('tenant.partials.preparation-notice')
    @endif
</x-tenant::layouts.auth>
