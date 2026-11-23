@props(['shopName' => null, 'ownerAccessUrl' => null])

<x-tenant::layouts.auth title="Connexion à la boutique" :shop-name="$shopName">
    @vite('resources/js/passkeys.js')
    <x-auth-header title="Administration de la boutique" description="Connectez-vous avec votre compte employé de cette boutique." />
    @if(session('status')) <flux:callout>{{ session('status') }}</flux:callout> @endif
    @if($errors->any()) @foreach($errors->all() as $error)<p class="text-red-500">{{ $error }}</p>@endforeach @endif
    <div x-data="{ error: '', busy: false, async login() {
        this.busy = true; this.error = '';
        try {
            const result = await window.Passkeys.verify({ routes: { options: '{{ route('tenant.passkeys.login.options', absolute: false) }}', submit: '{{ route('tenant.passkeys.login', absolute: false) }}' } });
            window.location.assign(result.redirect);
        } catch (error) { this.error = error.message; } finally { this.busy = false; }
    } }">
        <flux:button type="button" x-on:click="login()" x-bind:disabled="busy">Se connecter avec une clé d’accès</flux:button>
        <p x-show="error" x-text="error" class="text-red-600"></p>
    </div>
    <form method="POST" action="{{ route('tenant.login.store', absolute: false) }}" class="space-y-3">
        @csrf
        <label class="block">E-mail employé<input type="email" name="email" value="{{ old('email') }}" required autocomplete="username" class="mt-1 w-full rounded border p-2 text-zinc-900"></label>
        <label class="block">Mot de passe<input type="password" name="password" required autocomplete="current-password" class="mt-1 w-full rounded border p-2 text-zinc-900"></label>
        <flux:checkbox name="remember" value="1" label="Rester connecté" :checked="(bool) old('remember')" />
        <button class="w-full rounded border p-2">Se connecter à cette boutique</button>
    </form>
    <a class="underline text-sm" href="{{ route('tenant.password.request', absolute: false) }}">Mot de passe oublié ?</a>
    <p class="text-sm">Propriétaire de la boutique :</p>
    @if ($ownerAccessUrl !== null)
        <flux:button :href="$ownerAccessUrl" variant="primary">Continuer avec mon compte Aydra</flux:button>
    @endif
</x-tenant::layouts.auth>
