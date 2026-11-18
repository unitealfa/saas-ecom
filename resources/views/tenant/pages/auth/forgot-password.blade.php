<x-tenant::layouts.auth title="Mot de passe oublié">
    <flux:heading size="xl">Mot de passe oublié</flux:heading>
    @if(session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
    @if($errors->any())@foreach($errors->all() as $error)<p class="text-red-500">{{ $error }}</p>@endforeach@endif
    <form method="POST" action="{{ route('tenant.password.email', absolute: false) }}" class="space-y-3">
        @csrf
        <label class="block">E-mail du compte de cette boutique<input name="email" type="email" required class="mt-1 block w-full rounded border p-2 text-zinc-900"></label>
        <button class="rounded border p-3">Demander un lien</button>
    </form>
</x-tenant::layouts.auth>
