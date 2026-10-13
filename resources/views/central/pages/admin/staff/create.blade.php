<x-layouts::app title="Inviter un administrateur">
    <section class="mx-auto flex max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Inviter un administrateur SaaS</flux:heading>
        <flux:text>Le destinataire choisira son mot de passe avec le lien Fortify reçu par e-mail. Ce lien confirme aussi son adresse.</flux:text>
        @if ($errors->any())<flux:callout variant="danger">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <form method="POST" action="{{ route('central.staff.store') }}" class="flex flex-col gap-5">
            @csrf
            @include('central.pages.admin.staff.form', ['user' => null])
            <flux:heading level="2">Rôles Spatie</flux:heading>
            <flux:text>Seuls les rôles que vous pouvez déléguer sont proposés. Ils ne doivent partager aucune permission.</flux:text>
            @forelse ($roles as $role)
                <flux:checkbox name="roles[]" :value="$role->uuid" :label="$role->label" :checked="in_array($role->uuid, old('roles', []), true)" />
            @empty
                <flux:text>Aucun rôle attribuable. Le Super Admin doit préparer un rôle adapté.</flux:text>
            @endforelse
            <flux:button type="submit" variant="primary">Créer et inviter</flux:button>
            <flux:button :href="route('central.staff.index')">Retour au personnel</flux:button>
        </form>
    </section>
</x-layouts::app>
