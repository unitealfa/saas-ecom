<x-tenant::layouts.auth title="Accepter une invitation">
    <div class="space-y-4">
        <flux:heading size="xl">Rejoindre l’équipe</flux:heading>
        <flux:text>Vous êtes invité à rejoindre {{ $shopName }} avec l’adresse {{ $invitation->email }}.</flux:text>

        @foreach ($errors->all() as $error)
            <flux:text role="alert">{{ $error }}</flux:text>
        @endforeach

        @if ($switchAccount)
            <flux:text>Vous êtes connecté avec un autre compte dans cette boutique.</flux:text>
            <form method="POST" action="{{ route('tenant.invitations.switch-account', $invitation, false) }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <flux:button type="submit">Se déconnecter et continuer</flux:button>
            </form>
        @elseif (! $hasAccount)
            <flux:button variant="primary" :href="route('tenant.invitations.show', ['invitation' => $invitation, 'token' => $token, 'compte' => 1], false)">
                Accepter l’invitation
            </flux:button>
        @elseif (auth('tenant')->guest())
            <flux:text>Connectez-vous avec votre compte de cette boutique, puis rouvrez le lien d’invitation.</flux:text>
            <flux:button :href="route('tenant.login', absolute: false)">Se connecter</flux:button>
        @else
            <form method="POST" action="{{ route('tenant.invitations.accept', $invitation, false) }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <flux:button type="submit" variant="primary">Accepter l’invitation</flux:button>
            </form>
        @endif
    </div>
</x-tenant::layouts.auth>
