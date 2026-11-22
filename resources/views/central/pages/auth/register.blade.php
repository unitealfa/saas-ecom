<x-layouts::auth.split title="Créer un compte">
    <div class="flex flex-col gap-6">
        <div class="space-y-2">
            <flux:heading level="1" size="xl" class="aydra-login-heading">Créez votre compte</flux:heading>
            <flux:text>Renseignez vos informations pour rejoindre Aydra.</flux:text>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <flux:input name="last_name" label="Nom de famille" :value="old('last_name')" type="text" required autofocus autocomplete="family-name" />
            <flux:input name="first_name" label="Prénom" :value="old('first_name')" type="text" autocomplete="given-name" />
            <flux:field>
                <flux:label>Téléphone</flux:label>
                <flux:select name="country_uuid" label="Pays et indicatif du téléphone" required>
                    <option value="">Choisissez le pays du numéro</option>
                    @foreach ($countries as $country)
                        <option value="{{ $country->uuid }}" @selected(old('country_uuid') === $country->uuid)>{{ $country->name_fr }} ({{ $country->calling_code }})</option>
                    @endforeach
                </flux:select>
                <flux:input name="phone" label="Numéro local ou international" :value="old('phone')" type="tel" autocomplete="tel" placeholder="Ex. : 0779107446 pour l’Algérie" required />
                <flux:description>Le pays de votre profil sera initialisé avec ce choix. Votre numéro sera enregistré au format international.</flux:description>
            </flux:field>

            <!-- Email Address -->
            <flux:input
                name="email"
                label="Adresse e-mail"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <flux:input
                name="password"
                label="Mot de passe"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Votre mot de passe"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                label="Confirmer le mot de passe"
                type="password"
                required
                autocomplete="new-password"
                placeholder="Confirmez votre mot de passe"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    Créer mon compte
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>Vous avez déjà un compte ?</span>
            <flux:link :href="route('login')" wire:navigate>Se connecter</flux:link>
        </div>
    </div>
</x-layouts::auth.split>
