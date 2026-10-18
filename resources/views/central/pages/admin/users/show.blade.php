<x-layouts::app :title="__('Informations du compte')">
    <section class="mx-auto flex max-w-3xl flex-col gap-6">
        <div class="flex items-center gap-3"><flux:avatar :src="$user->profile_photo_url" :name="$user->name" size="lg" /><flux:heading size="xl" level="1">{{ $user->name }}</flux:heading></div>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if (session('warning'))<flux:callout variant="warning">{{ session('warning') }}</flux:callout>@endif
        @if ($errors->any())
            <flux:callout variant="danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </flux:callout>
        @endif
        <dl class="grid gap-4 sm:grid-cols-2">
            @foreach ([
                'Prénom' => $user->first_name, 'Nom de famille' => $user->last_name,
                'E-mail' => $user->email, 'Téléphone' => $user->phone,
                'Pays' => $user->country?->name_fr,
                'Langue du compte' => $user->getAttribute('locale'),
                'Type de compte' => $user->roles->isEmpty() ? 'Commerçant' : 'Personnel SaaS',
                'État' => $user->status->label(),
                'E-mail vérifié' => $user->hasVerifiedEmail() ? 'Oui' : 'Non',
                'Date de vérification de l’e-mail' => $user->email_verified_at?->format('d/m/Y H:i'),
                'Téléphone vérifié' => $user->getAttribute('phone_verified_at')?->format('d/m/Y H:i'),
                'WhatsApp vérifié' => $user->getAttribute('whatsapp_verified_at')?->format('d/m/Y H:i'),
                'Dernière connexion' => $user->getAttribute('last_login_at')?->format('d/m/Y H:i'),
                'Dernière modification' => $user->updated_at?->format('d/m/Y H:i'),
                'Double authentification confirmée' => $user->two_factor_confirmed_at?->format('d/m/Y H:i') ?? 'Non',
                'Création du compte' => $user->created_at?->format('d/m/Y H:i'),
            ] as $label => $value)
                <div><dt class="font-medium">{{ $label }}</dt><dd><flux:text>{{ $value ?? 'Non renseigné' }}</flux:text></dd></div>
            @endforeach
        </dl>
        @if ($user->roles->isNotEmpty())
            <flux:heading level="2" id="saas-roles">Rôles SaaS</flux:heading>
            <flux:text>{{ $user->roles->pluck('label')->join(', ') }}</flux:text>
        @endif
        @if ($user->roles->isEmpty())
            <flux:heading level="2">Boutiques</flux:heading>
            @forelse ($shops as $shop)
                <flux:text>{{ $shop->shop_name }} — {{ match ($shop->getAttribute('status')->value) {
                    1 => 'En préparation', 2 => 'Active', 3 => 'Inactive', 4 => 'Suspendue',
                    5 => 'Quota dépassé', 6 => 'Échec de préparation', 9 => 'Supprimée',
                } }}</flux:text>
            @empty
                <flux:text>Aucune boutique.</flux:text>
            @endforelse
        @endif
        @can('viewLegal', $user)
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
                    'Version du dossier professionnel' => $user->legal_profile_version,
                    'Vérification professionnelle' => match ($user->legal_verification_status) {
                        \App\Enums\VerificationStatusEnum::INCOMPLETE => 'Incomplet',
                        \App\Enums\VerificationStatusEnum::PENDING => 'À vérifier',
                        \App\Enums\VerificationStatusEnum::VERIFIED => 'Vérifié',
                        \App\Enums\VerificationStatusEnum::FAILED => 'Refusé',
                        \App\Enums\VerificationStatusEnum::EXPIRED => 'Expiré',
                        null => 'Non renseignée',
                    },
                    'Date de vérification professionnelle' => $user->getAttribute('legal_verified_at')?->format('d/m/Y H:i'),
                    'Dossier vérifié par' => $user->legalVerifier?->name,
                ] as $label => $value)
                    <div><dt class="font-medium">{{ $label }}</dt><dd><flux:text>{{ $value ?? 'Non renseigné' }}</flux:text></dd></div>
                @endforeach
            </dl>
            @if ($user->legal_profile_version !== null && ! $user->trashed())
                <div class="flex flex-col gap-4">
                    @foreach (['validateLegal' => ['central.users.legal.validate', 'Valider le dossier'], 'rejectLegal' => ['central.users.legal.reject', 'Rejeter le dossier']] as $ability => [$action, $label])
                        @can($ability, $user)
                            <form method="POST" action="{{ route($action, $user) }}" class="flex flex-col gap-3">
                                @csrf
                                <input type="hidden" name="legal_profile_version" value="{{ $user->legal_profile_version }}">
                                <flux:textarea name="reason" label="Motif (facultatif)" />
                                <flux:button type="submit">{{ $label }}</flux:button>
                            </form>
                        @endcan
                    @endforeach
                </div>
            @endif
        @endcan
        <flux:heading level="2">Abonnements</flux:heading>
        @forelse ($subscriptions as $subscription)
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading level="3">{{ $subscription->plan?->getAttribute('name') ?? 'Offre indisponible' }}</flux:heading>
                <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        'État' => match ($subscription->status) {
                            \App\Enums\Central\Subscriptions\StatusEnum::SCHEDULED => 'Prévu',
                            \App\Enums\Central\Subscriptions\StatusEnum::TRIAL => 'Essai',
                            \App\Enums\Central\Subscriptions\StatusEnum::ACTIVE => 'Actif',
                            \App\Enums\Central\Subscriptions\StatusEnum::EXPIRED => 'Expiré',
                            \App\Enums\Central\Subscriptions\StatusEnum::CANCELLED => 'Annulé',
                        },
                        'Période' => $subscription->period === \App\Enums\Central\Subscriptions\PeriodEnum::MONTHLY ? 'Mensuelle' : 'Annuelle',
                        'Montant convenu' => $subscription->getAttribute('agreed_amount'),
                        'Début' => $subscription->getAttribute('started_at')?->format('d/m/Y'),
                        'Début de période' => $subscription->period_starts_at?->format('d/m/Y'),
                        'Fin de période' => $subscription->period_ends_at?->format('d/m/Y') ?? 'Sans échéance',
                        'Fin de l’essai' => $subscription->getAttribute('trial_ends_at')?->format('d/m/Y'),
                        'Fin de l’abonnement' => $subscription->getAttribute('ended_at')?->format('d/m/Y'),
                        'Renouvellement automatique' => $subscription->getAttribute('auto_renew') ? 'Oui' : 'Non',
                    ] as $label => $value)
                        <div><dt class="font-medium">{{ $label }}</dt><dd><flux:text>{{ $value ?? 'Non renseigné' }}</flux:text></dd></div>
                    @endforeach
                </dl>
            </div>
        @empty
            <flux:text>Aucun abonnement enregistré.</flux:text>
        @endforelse
        {{ $subscriptions->links() }}
        <div class="flex flex-wrap gap-3">
            @include('central.pages.admin.users.lifecycle-actions')
            @can('viewHistory', $user)
                <flux:button :href="route('central.users.history', $user)">Historique administratif</flux:button>
            @endcan
            @can('update', $user)
                <flux:button :href="route('central.users.edit', $user)" variant="primary">Modifier</flux:button>
            @endcan
            @can('resendInvitation', $user)
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
