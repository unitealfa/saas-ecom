<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

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
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
