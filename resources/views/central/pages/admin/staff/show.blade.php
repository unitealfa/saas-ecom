<x-layouts::app title="Informations du personnel">
    <section class="mx-auto flex w-full max-w-screen-xl flex-col gap-6">
        <header class="flex flex-wrap items-center gap-4">
            <flux:avatar :src="$user->profile_photo_url" :name="$user->name" size="lg" />
            <div class="min-w-0 space-y-2">
                <h1 class="aydra-heading break-words text-3xl font-semibold tracking-tight sm:text-4xl">{{ $user->name }}</h1>
                <div class="flex flex-wrap items-center gap-2"><span class="aydra-muted text-sm">Personnel SaaS</span>
                    <flux:badge size="sm" :color="match ($user->status) {
                        \App\Enums\Central\Users\StatusEnum::ACTIVE => 'green', \App\Enums\Central\Users\StatusEnum::PENDING => 'amber',
                        \App\Enums\Central\Users\StatusEnum::SUSPENDED, \App\Enums\Central\Users\StatusEnum::REJECTED => 'red', default => 'zinc',
                    }">{{ $user->status->label() }}</flux:badge>
                </div>
            </div>
        </header>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if (session('warning'))<flux:callout variant="warning">{{ session('warning') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <section class="aydra-users-panel p-5 sm:p-6" aria-labelledby="staff-account-information">
        <h2 id="staff-account-information" class="aydra-heading text-lg font-semibold">Informations du compte</h2>
        <p class="aydra-muted mt-1 text-sm">Coordonnées, vérification et activité du compte.</p>
        <dl class="aydra-detail-grid mt-6 grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                'Prénom' => $user->first_name, 'Nom de famille' => $user->last_name, 'E-mail' => $user->email,
                'Téléphone' => $user->phone, 'Pays' => $user->country?->name_fr,
                'Pays du téléphone' => $user->phoneCountry?->name_fr,
                'Langue' => $user->getAttribute('locale'), 'État' => $user->status->label(),
                'E-mail vérifié' => $user->hasVerifiedEmail() ? 'Oui' : 'Non',
                'Vérification de l’e-mail' => $user->email_verified_at?->format('d/m/Y H:i'),
                'Dernière connexion' => $user->getAttribute('last_login_at')?->format('d/m/Y H:i'),
                'Création' => $user->created_at?->format('d/m/Y H:i'),
                'Dernière modification' => $user->updated_at?->format('d/m/Y H:i'),
                'Double authentification confirmée' => $user->two_factor_confirmed_at?->format('d/m/Y H:i'),
            ] as $label => $value)
                <div><dt class="font-medium">{{ $label }}</dt><dd><flux:text>{{ $value ?? 'Non renseigné' }}</flux:text></dd></div>
            @endforeach
        </dl>
        </section>
        <section class="aydra-users-panel p-5 sm:p-6" aria-labelledby="staff-roles">
        <h2 id="staff-roles" class="aydra-heading mb-4 text-lg font-semibold">Rôles SaaS</h2>
        @foreach ($user->roles as $role)
            <div class="flex flex-col gap-3">
                <flux:text>{{ $role->label }} — attribué le {{ $role->pivot->assigned_at->format('d/m/Y H:i') }}</flux:text>
                @can('renewStaffAssignments', $user)
                    <form method="POST" action="{{ route('central.staff.roles.renew', [$user, $role]) }}" class="flex flex-col gap-3">
                        @csrf
                        <input type="hidden" name="expected_assigned_at" value="{{ $role->pivot->assigned_at->toIso8601String() }}">
                        <flux:text>Renouvelle les droits individuels encore attribués et présents dans ce profil. Aucun droit retiré n’est réattribué.</flux:text>
                        <flux:textarea name="reason" label="Motif du renouvellement" required />
                        <flux:button type="submit">Renouveler ce rôle</flux:button>
                    </form>
                @endcan
            </div>
        @endforeach
        </section>
        <section class="aydra-users-panel p-5 sm:p-6" aria-labelledby="staff-actions">
            <h2 id="staff-actions" class="aydra-heading mb-4 text-lg font-semibold">Actions du compte</h2>
            <div class="flex flex-wrap items-center gap-3">@include('central.pages.admin.staff.actions')
                <flux:button :href="route('central.staff.index')">Retour au personnel</flux:button>
            </div>
        </section>
    </section>
</x-layouts::app>
