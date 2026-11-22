<x-layouts::app title="Inviter un administrateur">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Inviter un administrateur SaaS</h1><p class="aydra-muted text-sm sm:text-base">Renseignez ses coordonnées et choisissez ses rôles.</p></header>
        <flux:callout>Le destinataire choisira son mot de passe avec le lien reçu par e-mail. Ce lien confirme aussi son adresse.</flux:callout>
        @if ($errors->any())<flux:callout variant="danger">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <form method="POST" action="{{ route('central.staff.store') }}" class="flex flex-col gap-5">
            @csrf
            <section class="aydra-users-panel p-5 sm:p-6" aria-labelledby="staff-create-identity">
                <h2 id="staff-create-identity" class="aydra-heading text-lg font-semibold">Identité et coordonnées</h2>
                <div class="mt-6 grid gap-5 sm:grid-cols-2">@include('central.pages.admin.staff.form', ['user' => null])</div>
            </section>
            <section class="aydra-users-panel flex flex-col gap-3 p-5 sm:p-6" aria-labelledby="staff-create-roles">
            <h2 id="staff-create-roles" class="aydra-heading text-lg font-semibold">Rôles du personnel <span class="aydra-required-mark" aria-label="obligatoire">*</span></h2>
            <flux:text>Sélectionnez au moins un rôle. Seuls les rôles que vous pouvez déléguer sont proposés. Ils ne doivent partager aucune permission.</flux:text>
            @forelse ($roles as $role)
                <flux:checkbox name="roles[]" :value="$role->uuid" :label="$role->label" :checked="in_array($role->uuid, old('roles', []), true)" />
            @empty
                <flux:text>Aucun rôle attribuable. Le Super Admin doit préparer un rôle adapté.</flux:text>
            @endforelse
            </section>
            <div class="flex flex-wrap items-center gap-3">
            <flux:button type="submit" variant="primary">Créer et inviter</flux:button>
            <flux:button :href="route('central.staff.index')">Retour au personnel</flux:button>
            </div>
        </form>
    </section>
</x-layouts::app>
