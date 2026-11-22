<x-layouts::auth.split title="Connexion">
    <div class="flex flex-col gap-6">
        <div class="space-y-2">
            <flux:heading level="1" size="xl" class="aydra-login-heading">Content de vous retrouver</flux:heading>
            <flux:text>Connectez-vous à votre espace Aydra.</flux:text>
        </div>

        @if (session('registration_created'))
            <flux:callout variant="success" icon="check-circle" role="status">Votre compte a bien été créé. Connectez-vous pour vérifier votre adresse e-mail.</flux:callout>
            @unless (session('registration_email_sent'))
                <flux:callout variant="warning" role="alert">L’e-mail n’a pas pu être envoyé. Après connexion, vous pourrez demander un nouvel envoi.</flux:callout>
            @endunless
        @endif

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-central::components.auth.passkey-verify label="Se connecter avec une clé d’accès" loading-label="Connexion en cours…" separator="Ou avec votre e-mail" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                label="Adresse e-mail"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    label="Mot de passe"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="Votre mot de passe"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        Mot de passe oublié ?
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" label="Se souvenir de moi" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    Se connecter
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>Vous n’avez pas encore de compte ?</span>
            <flux:link :href="route('register')" wire:navigate>Créer un compte</flux:link>
        </div>
    </div>
</x-layouts::auth.split>
