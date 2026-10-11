<x-layouts::app :title="__('Modifier le compte')">
    <section class="mx-auto flex max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Modifier {{ $user->name }}</flux:heading>
        <flux:text>Un changement d’e-mail demande une nouvelle vérification. Le mot de passe et les rôles ne sont pas modifiés par ce formulaire.</flux:text>
        @if ($errors->any())
            <flux:callout variant="danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </flux:callout>
        @endif
        <form method="POST" action="{{ route('central.users.update', $user) }}" class="flex flex-col gap-5">
            @csrf
            @method('PUT')
            <flux:input name="first_name" label="Prénom" :value="old('first_name', $user->first_name)" autocomplete="given-name" />
            <flux:input name="last_name" label="Nom de famille" :value="old('last_name', $user->last_name)" autocomplete="family-name" required />
            <flux:input name="email" type="email" label="Adresse e-mail" :value="old('email', $user->email)" autocomplete="email" required />
            <flux:input name="phone" type="tel" label="Téléphone" :value="old('phone', $user->phone)" autocomplete="tel" />
            <flux:select name="country_uuid" label="Pays" required>
                @foreach ($countries as $country)
                    <option value="{{ $country->uuid }}" @selected(old('country_uuid', $user->country->uuid) === $country->uuid)>{{ $country->name_fr }}</option>
                @endforeach
            </flux:select>
            <div class="flex flex-wrap gap-3">
                <flux:button type="submit" variant="primary">Enregistrer</flux:button>
                @can('view', $user)
                    <flux:button :href="route('central.users.show', $user)">Retour à la fiche</flux:button>
                @else
                    <flux:button :href="route('dashboard')">Retour au dashboard</flux:button>
                @endcan
            </div>
        </form>
    </section>
</x-layouts::app>
