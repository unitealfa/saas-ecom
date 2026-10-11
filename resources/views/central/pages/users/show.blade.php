<x-layouts::app :title="__('Informations du compte')">
    <section class="mx-auto flex max-w-3xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $user->name }}</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if (session('warning'))<flux:callout variant="warning">{{ session('warning') }}</flux:callout>@endif
        @if ($errors->any())
            <flux:callout variant="danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </flux:callout>
        @endif
        <dl class="grid gap-4 sm:grid-cols-2">
            @foreach ([
                'Identifiant public' => $user->uuid,
                'Prénom' => $user->first_name, 'Nom de famille' => $user->last_name,
                'E-mail' => $user->email, 'Téléphone' => $user->phone,
                'Pays' => $user->country->name_fr,
                'Type de compte' => $user->roles->isEmpty() ? 'Commerçant' : 'Personnel SaaS',
                'État' => match ($user->status) {
                    \App\Enums\Users\StatusEnum::ACTIVE => 'Actif',
                    \App\Enums\Users\StatusEnum::INACTIVE => 'Inactif',
                    \App\Enums\Users\StatusEnum::SUSPENDED => 'Suspendu',
                    \App\Enums\Users\StatusEnum::DELETED => 'Supprimé',
                },
                'E-mail vérifié' => $user->hasVerifiedEmail() ? 'Oui' : 'Non',
                'Création du compte' => $user->created_at?->format('d/m/Y H:i'),
            ] as $label => $value)
                <div><dt class="font-medium">{{ $label }}</dt><dd><flux:text>{{ $value ?? 'Non renseigné' }}</flux:text></dd></div>
            @endforeach
        </dl>
        <flux:heading level="2">Rôles SaaS</flux:heading>
        <flux:text>{{ $user->roles->pluck('label')->join(', ') ?: 'Aucun rôle SaaS' }}</flux:text>
        @if ($user->roles->isEmpty())
            <flux:heading level="2">Boutiques</flux:heading>
            @forelse ($shops as $shop)
                <flux:text>{{ $shop->shop_name }}</flux:text>
            @empty
                <flux:text>Aucune boutique.</flux:text>
            @endforelse
            <flux:heading level="2">Informations professionnelles</flux:heading>
            <dl class="grid gap-4 sm:grid-cols-2">
                @foreach ([
                    'Forme juridique' => $user->getAttribute('legal_form'),
                    'Activité' => $user->getAttribute('activity_nature'),
                    'NIF' => $user->getAttribute('nif'), 'NIS' => $user->getAttribute('nis'),
                    'Registre de commerce' => $user->getAttribute('registration_number'),
                    'Carte artisan' => $user->getAttribute('artisan_card_number'),
                    'Adresse professionnelle' => $user->getAttribute('legal_address'),
                    'Capital social' => $user->getAttribute('share_capital'),
                ] as $label => $value)
                    <div><dt class="font-medium">{{ $label }}</dt><dd><flux:text>{{ $value ?? 'Non renseigné' }}</flux:text></dd></div>
                @endforeach
            </dl>
        @endif
        <div class="flex flex-wrap gap-3">
            @can('update', $user)
                <flux:button :href="route('central.users.edit', $user)" variant="primary">Modifier</flux:button>
                @unless ($user->hasVerifiedEmail())
                    <form method="POST" action="{{ route('central.users.invitation', $user) }}">
                        @csrf
                        <flux:button type="submit">Renvoyer l’invitation</flux:button>
                    </form>
                @endunless
            @endcan
            @can('assignRoles', $user)
                <flux:button :href="route('central.users.roles.edit', $user)">Rôles</flux:button>
            @endcan
            @can('create', [\App\Models\Central\Tenant::class, $user])
                <flux:button :href="route('central.users.tenants.create', $user)">Boutiques</flux:button>
            @endcan
            @can('viewAny', \App\Models\User::class)
                <flux:button :href="route('central.users.index')">Retour aux utilisateurs</flux:button>
            @else
                <flux:button :href="route('dashboard')">Retour au dashboard</flux:button>
            @endcan
        </div>
    </section>
</x-layouts::app>
