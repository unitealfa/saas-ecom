<x-tenant::layouts.auth title="Accepter une invitation">
    <flux:heading size="xl">Rejoindre l’équipe</flux:heading>
    <p>Invitation à rejoindre cette boutique pour {{ $invitation->email }}.</p>
    @if($errors->any())@foreach($errors->all() as $error)<p class="text-red-500">{{ $error }}</p>@endforeach@endif
    <form method="POST" action="{{ route('tenant.invitations.accept', $invitation, false) }}" class="space-y-3">
        @csrf <input type="hidden" name="token" value="{{ $token }}">
        @guest('tenant')
            <p>Si vous possédez déjà un compte local pour cette boutique, connectez-vous d'abord puis revenez sur le lien d'invitation.</p>
            <label class="block">Nom <input name="last_name" maxlength="150" class="block w-full rounded border p-2 text-zinc-900"></label>
            <label class="block">Prénom <input name="first_name" maxlength="150" class="block w-full rounded border p-2 text-zinc-900"></label>
            <label class="block">Mot de passe (12 caractères, majuscule, minuscule, chiffre et symbole)
                <input name="password" type="password" autocomplete="new-password" class="block w-full rounded border p-2 text-zinc-900"></label>
            <label class="block">Confirmation <input name="password_confirmation" type="password" class="block w-full rounded border p-2 text-zinc-900"></label>
        @endguest
        <button class="rounded border p-3">Accepter l’invitation</button>
    </form>
</x-tenant::layouts.auth>
