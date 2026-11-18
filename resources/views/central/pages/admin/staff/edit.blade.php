<x-layouts::app title="Modifier un administrateur">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="space-y-2"><h1 class="aydra-heading break-words text-3xl font-semibold tracking-tight sm:text-4xl">Modifier {{ $user->name }}</h1><p class="aydra-muted text-sm sm:text-base">Mettez à jour les informations de ce compte.</p></header>
        <flux:callout>Les rôles et l’état se gèrent séparément. Un changement d’e-mail demande une nouvelle vérification.</flux:callout>
        @if ($errors->any())<flux:callout variant="danger">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <form method="POST" action="{{ route('central.staff.update', $user) }}" class="flex flex-col gap-5">
            @csrf @method('PUT')
            <section class="aydra-users-panel p-5 sm:p-6" aria-labelledby="staff-edit-identity">
                <h2 id="staff-edit-identity" class="aydra-heading text-lg font-semibold">Identité et coordonnées</h2>
                <div class="mt-6 grid gap-5 sm:grid-cols-2">@include('central.pages.admin.staff.form')</div>
            </section>
            <div class="flex flex-wrap items-center gap-3">
            <flux:button type="submit" variant="primary">Enregistrer</flux:button>
            <flux:button :href="route('central.staff.show', $user)">Retour à la fiche</flux:button>
            </div>
        </form>
    </section>
</x-layouts::app>
