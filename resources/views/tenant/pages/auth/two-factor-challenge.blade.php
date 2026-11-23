<x-tenant::layouts.auth title="Double authentification">
    <flux:heading size="xl">Double authentification</flux:heading>
    <flux:text>Saisissez le code de votre application ou un code de récupération.</flux:text>
    <form method="POST" action="{{ route('tenant.two-factor.verify', absolute: false) }}" class="mt-6 space-y-4">
        @csrf
        <flux:input name="code" label="Code" autocomplete="one-time-code" required />
        <flux:button type="submit" variant="primary">Continuer</flux:button>
    </form>
</x-tenant::layouts.auth>
