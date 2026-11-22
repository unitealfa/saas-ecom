<x-tenant::layouts.auth title="Créer mon compte">
    <div class="space-y-4">
        <flux:heading size="xl">Créer mon compte</flux:heading>
        <flux:text>Boutique {{ $shopName }}</flux:text>
        <flux:text>Adresse de l’invitation : {{ $invitation->email }}</flux:text>

        @foreach ($errors->all() as $error)
            <flux:text role="alert">{{ $error }}</flux:text>
        @endforeach

        <form method="POST" action="{{ route('tenant.invitations.accept', $invitation, false) }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <flux:input name="last_name" label="Nom" :value="old('last_name')" maxlength="150" autocomplete="family-name" required />
            <flux:input name="first_name" label="Prénom (facultatif)" :value="old('first_name')" maxlength="150" autocomplete="given-name" />
            <flux:input name="password" label="Mot de passe" type="password" autocomplete="new-password" minlength="12" required />
            <flux:text>Au moins 12 caractères, avec une majuscule, une minuscule, un chiffre et un symbole.</flux:text>
            <flux:input name="password_confirmation" label="Confirmer le mot de passe" type="password" autocomplete="new-password" required />
            <flux:button type="submit" variant="primary">Créer mon compte et rejoindre l’équipe</flux:button>
        </form>
    </div>
</x-tenant::layouts.auth>
