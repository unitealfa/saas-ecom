<x-tenant::layouts.auth title="Invitation indisponible">
    <flux:heading size="xl">Invitation indisponible</flux:heading>
    <p>Ce lien d'invitation n'est plus utilisable. Demandez une nouvelle invitation à l'administration de votre boutique.</p>
    <a class="underline" href="{{ route('tenant.login', absolute: false) }}">Connexion à cette boutique</a>
</x-tenant::layouts.auth>
