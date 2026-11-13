@props(['shopName' => null, 'ownerAccessUrl' => null])

<x-tenant::layouts.auth title="Connexion à la boutique" :shop-name="$shopName">
    <x-auth-header title="Administration de la boutique" description="Utilisez votre compte propriétaire Aydra pour accéder à cette boutique." />
    @if ($ownerAccessUrl !== null)
        <flux:button :href="$ownerAccessUrl" variant="primary">Continuer avec mon compte Aydra</flux:button>
    @endif
</x-tenant::layouts.auth>
