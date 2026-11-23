<x-tenant::layouts.app :title="__('Paramètres du compte')" :shop-name="$dashboard->shopName" :user-name="$dashboard->userName">
    @vite('resources/js/passkeys.js')
    <div class="flex max-w-xl flex-col gap-8">
        <flux:heading size="xl">Paramètres du compte</flux:heading>
        @if (session('status'))
            <flux:text role="status">{{ session('status') }}</flux:text>
        @endif
        @foreach ($errors->all() as $error)<p class="text-red-600" role="alert">{{ $error }}</p>@endforeach
        @foreach ($errors->confirmTwoFactorAuthentication->all() as $error)<p class="text-red-600" role="alert">{{ $error }}</p>@endforeach
        <section class="space-y-4">
            <flux:heading size="lg">Profil</flux:heading>
            <flux:avatar :src="$user->profile_photo_url" :name="$dashboard->userName" />
            <form method="POST" action="{{ route('tenant.profile-photo.update', absolute: false) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <flux:input type="file" name="photo" label="Photo de profil" accept="image/jpeg,image/png,image/webp" required />
                <flux:text>JPG, PNG ou WebP, 2 Mo et 4096 pixels maximum par côté.</flux:text>
                <flux:button type="submit">Enregistrer la photo</flux:button>
            </form>
            @if ($user->profilePhoto)
                <form method="POST" action="{{ route('tenant.profile-photo.destroy', absolute: false) }}">
                    @csrf
                    @method('DELETE')
                    <flux:button type="submit">Retirer la photo</flux:button>
                </form>
            @endif
            <form method="POST" action="{{ route('tenant.settings.profile', absolute: false) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <flux:input name="first_name" label="Prénom" :value="old('first_name', $user->first_name)" maxlength="150" />
                <flux:input name="last_name" label="Nom" :value="old('last_name', $user->last_name)" maxlength="150" required />
                <flux:select name="country_uuid" label="Pays du profil" placeholder="Choisir un pays">
                    <option value="">Non renseigné</option>
                    @foreach ($countries as $country)<option value="{{ $country->uuid }}" @selected(old('country_uuid', $user->country_uuid) === $country->uuid)>{{ $country->name_fr }}</option>@endforeach
                </flux:select>
                <flux:select name="phone_country_uuid" label="Pays du téléphone">
                    <option value="">Non renseigné</option>
                    @foreach ($countries as $country)<option value="{{ $country->uuid }}" @selected(old('phone_country_uuid', $user->phone_country_uuid) === $country->uuid)>{{ $country->name_fr }} ({{ $country->calling_code }})</option>@endforeach
                </flux:select>
                <flux:input name="phone" label="Téléphone" :value="old('phone', $user->phone)" maxlength="32" />
                <flux:button type="submit" variant="primary">Enregistrer</flux:button>
            </form>
            <form method="POST" action="{{ route('tenant.settings.email.request', absolute: false) }}" class="space-y-4">
                @csrf
                <flux:input name="email" type="email" label="Nouvel e-mail" :value="$user->email" required />
                <flux:input name="current_password" type="password" label="Mot de passe actuel" required />
                <flux:button type="submit">Confirmer une nouvelle adresse</flux:button>
            </form>
        </section>
        <section class="space-y-4">
            <flux:heading size="lg">Sécurité</flux:heading>
            <form method="POST" action="{{ route('tenant.settings.password', absolute: false) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <flux:input name="current_password" type="password" label="Mot de passe actuel" autocomplete="current-password" required />
                <flux:input name="password" type="password" label="Nouveau mot de passe" autocomplete="new-password" required />
                <flux:input name="password_confirmation" type="password" label="Confirmer le mot de passe" autocomplete="new-password" required />
                <flux:button type="submit" variant="primary">Enregistrer le mot de passe</flux:button>
            </form>
            <flux:heading>Double authentification</flux:heading>
            @if (session('tenant_recovery_codes'))
                <flux:text>Conservez ces codes de récupération dans un endroit sûr :</flux:text>
                <ul>@foreach (session('tenant_recovery_codes') as $code)<li><code>{{ $code }}</code></li>@endforeach</ul>
            @endif
            @if ($user->two_factor_secret && ! $user->hasEnabledTwoFactorAuthentication() && session('tenant_two_factor_setup_until', 0) >= now()->timestamp)
                {!! $user->twoFactorQrCodeSvg() !!}
                <form method="POST" action="{{ route('tenant.settings.two-factor.confirm', absolute: false) }}" class="space-y-4">
                    @csrf
                    <flux:input type="password" name="current_password" label="Mot de passe actuel" required />
                    <flux:input name="code" label="Code de l’application" required />
                    <flux:button type="submit">Confirmer</flux:button>
                </form>
            @endif
            <form method="POST" action="{{ $user->hasEnabledTwoFactorAuthentication() ? route('tenant.settings.two-factor.disable', absolute: false) : route('tenant.settings.two-factor.enable', absolute: false) }}" class="space-y-4">
                @csrf
                @if ($user->hasEnabledTwoFactorAuthentication()) @method('DELETE') @endif
                <flux:input type="password" name="current_password" label="Mot de passe actuel" required />
                <flux:button type="submit">{{ $user->hasEnabledTwoFactorAuthentication() ? 'Désactiver la double authentification' : 'Configurer la double authentification' }}</flux:button>
            </form>
            @if ($user->hasEnabledTwoFactorAuthentication())
                <form method="POST" action="{{ route('tenant.settings.two-factor.recovery', absolute: false) }}" class="space-y-4">
                    @csrf
                    <flux:input type="password" name="current_password" label="Mot de passe actuel" required />
                    <flux:button type="submit">Créer de nouveaux codes de récupération</flux:button>
                </form>
            @endif
            <flux:heading>Clés d’accès</flux:heading>
            @foreach ($user->passkeys as $passkey)
                <form method="POST" action="{{ route('tenant.passkeys.destroy', $passkey->uuid, absolute: false) }}" class="space-y-2">
                    @csrf
                    @method('DELETE')
                    <flux:text>{{ $passkey->name }}</flux:text>
                    <flux:input name="current_password" type="password" label="Mot de passe actuel" required />
                    <flux:button type="submit">Supprimer cette clé</flux:button>
                </form>
            @endforeach
            <div x-data="{ name: '', password: '', error: '', busy: false, async add() {
                this.busy = true; this.error = '';
                try {
                    const response = await fetch('{{ route('tenant.passkeys.prepare', absolute: false) }}', {
                        method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ current_password: this.password })
                    });
                    if (!response.ok) throw new Error('Mot de passe incorrect ou opération impossible.');
                    await window.Passkeys.register({ name: this.name, routes: { options: '{{ route('tenant.passkeys.options', absolute: false) }}', submit: '{{ route('tenant.passkeys.register', absolute: false) }}' } });
                    window.location.reload();
                } catch (error) { this.error = error.message; } finally { this.busy = false; }
            } }" class="space-y-3">
                <flux:input x-model="name" label="Nom de la clé" />
                <flux:input x-model="password" type="password" label="Mot de passe actuel" />
                <flux:button type="button" x-on:click="add()" x-bind:disabled="busy || !name || !password">Ajouter une clé d’accès</flux:button>
                <p x-show="error" x-text="error" class="text-red-600"></p>
            </div>
        </section>
        <section class="space-y-4">
            <flux:heading size="lg">Apparence</flux:heading>
            <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                <flux:radio value="light">Clair</flux:radio>
                <flux:radio value="dark">Sombre</flux:radio>
                <flux:radio value="system">Système</flux:radio>
            </flux:radio.group>
        </section>
        <section class="space-y-4">
            <flux:heading size="lg">Fermer mon compte boutique</flux:heading>
            <flux:text>Votre accès à cette boutique sera supprimé. L’historique de la boutique sera conservé.</flux:text>
            <form method="POST" action="{{ route('tenant.settings.destroy', absolute: false) }}" class="space-y-4">
                @csrf
                @method('DELETE')
                <flux:input name="current_password" type="password" label="Mot de passe actuel" required />
                <flux:checkbox name="confirm" value="1" label="Je confirme la fermeture de mon compte dans cette boutique" required />
                <flux:button type="submit" variant="danger">Fermer mon compte</flux:button>
            </form>
        </section>
    </div>
</x-tenant::layouts.app>
