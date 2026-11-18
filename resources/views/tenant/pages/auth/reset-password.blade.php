<x-tenant::layouts.auth title="Réinitialiser le mot de passe">
    <flux:heading size="xl">Nouveau mot de passe</flux:heading>
    @if($errors->any())@foreach($errors->all() as $error)<p class="text-red-500">{{ $error }}</p>@endforeach@endif
    <form method="POST" action="{{ route('tenant.password.update', absolute: false) }}" class="space-y-3">
        @csrf <input type="hidden" name="token" value="{{ $token }}">
        <label class="block">E-mail<input name="email" type="email" value="{{ $email }}" required class="mt-1 block w-full rounded border p-2 text-zinc-900"></label>
        <label class="block">Mot de passe<input name="password" type="password" required autocomplete="new-password" class="mt-1 block w-full rounded border p-2 text-zinc-900"></label>
        <label class="block">Confirmer<input name="password_confirmation" type="password" required autocomplete="new-password" class="mt-1 block w-full rounded border p-2 text-zinc-900"></label>
        <button class="rounded border p-3">Enregistrer</button>
    </form>
</x-tenant::layouts.auth>
