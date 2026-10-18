<x-layouts::auth title="Lien de vérification indisponible">
    <div class="space-y-6">
        <flux:heading size="xl">Ce lien est expiré ou invalide</flux:heading>
        <flux:text>Votre adresse n’a pas été vérifiée par ce lien. Connectez-vous au compte concerné pour demander un nouvel e-mail. Si votre adresse est déjà vérifiée, vous serez guidé vers la prochaine étape.</flux:text>
        <flux:button :href="auth('central')->check() ? route('registration.status') : route('login')" variant="primary">Continuer avec mon compte</flux:button>
        @auth('central')
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:button type="submit" variant="ghost">Se connecter avec un autre compte</flux:button>
            </form>
        @endauth
    </div>
</x-layouts::auth>
