<x-layouts::app title="Modifier un administrateur">
    <section class="mx-auto flex max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Modifier {{ $user->name }}</flux:heading>
        <flux:button :href="route('central.users.contacts.index', $user)">Gérer les e-mails et téléphones</flux:button>
        <flux:text>Les rôles et l’état se gèrent séparément. Un changement d’e-mail demande une nouvelle vérification.</flux:text>
        @if ($errors->any())<flux:callout variant="danger">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <form method="POST" action="{{ route('central.staff.update', $user) }}" class="flex flex-col gap-5">
            @csrf @method('PUT')
            @include('central.pages.admin.staff.form')
            <flux:button type="submit" variant="primary">Enregistrer</flux:button>
            <flux:button :href="route('central.staff.show', $user)">Retour à la fiche</flux:button>
        </form>
    </section>
</x-layouts::app>
