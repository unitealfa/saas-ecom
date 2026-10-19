@php
    $prefix = $admin ? 'central.users.contacts.' : 'central.contacts.';
    $parameters = $admin ? ['user' => $target] : [];
@endphp
<flux:text>Les anciennes coordonnées restent secondaires lorsque vous remplacez une coordonnée principale. Une adresse secondaire doit être vérifiée pour servir à la connexion ou à la récupération du mot de passe.</flux:text>
<div class="space-y-4">
    @foreach ($contacts as $contact)
        <div class="flex flex-wrap items-center gap-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:text>{{ $contact->kind === 'email' ? 'E-mail' : 'Téléphone' }} : {{ $contact->value }}</flux:text>
            @if ($contact->is_primary)<flux:badge>Principal</flux:badge>@endif
            <flux:badge>{{ $contact->verified_at ? 'Vérifié' : 'Non vérifié' }}</flux:badge>
            @if (! $contact->is_primary)
                @if ($contact->kind !== 'email' || $contact->verified_at)
                    <form method="POST" action="{{ route($prefix.'primary', [...$parameters, 'contact' => $contact]) }}">
                        @csrf @method('PUT')
                        <flux:button type="submit">Définir comme principal</flux:button>
                    </form>
                @endif
                <form method="POST" action="{{ route($prefix.'destroy', [...$parameters, 'contact' => $contact]) }}">
                    @csrf @method('DELETE')
                    <flux:button type="submit" variant="danger">Supprimer</flux:button>
                </form>
            @endif
            @if ($contact->kind === 'email' && ! $contact->verified_at)
                <form method="POST" action="{{ route($prefix.'resend', [...$parameters, 'contact' => $contact]) }}">
                    @csrf
                    <flux:button type="submit">Renvoyer la vérification</flux:button>
                </form>
            @endif
        </div>
    @endforeach
    <form method="POST" action="{{ route($prefix.'store', $parameters) }}" class="space-y-4">
        @csrf
        <flux:heading level="2">Ajouter une coordonnée</flux:heading>
        <flux:select name="kind" label="Type" required>
            <option value="email" @selected(old('kind') === 'email')>E-mail</option>
            <option value="phone" @selected(old('kind') === 'phone')>Téléphone</option>
        </flux:select>
        <flux:input name="value" label="Adresse e-mail ou numéro" :value="old('value')" required />
        <flux:select name="country_uuid" label="Pays du numéro (téléphone uniquement)">
            <option value="">Choisir un pays</option>
            @foreach ($countries as $country)
                <option value="{{ $country->uuid }}" @selected(old('country_uuid') === $country->uuid)>{{ $country->name_fr }} ({{ $country->calling_code }})</option>
            @endforeach
        </flux:select>
        <flux:button type="submit" variant="primary">Ajouter</flux:button>
    </form>
</div>
