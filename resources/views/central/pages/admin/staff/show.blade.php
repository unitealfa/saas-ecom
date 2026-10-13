<x-layouts::app title="Informations du personnel">
    <section class="mx-auto flex max-w-3xl flex-col gap-6">
        <div class="flex items-center gap-3"><flux:avatar :src="$user->profile_photo_url" :name="$user->name" size="lg" /><flux:heading size="xl" level="1">{{ $user->name }}</flux:heading></div>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if (session('warning'))<flux:callout variant="warning">{{ session('warning') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <dl class="grid gap-4 sm:grid-cols-2">
            @foreach ([
                'Prénom' => $user->first_name, 'Nom de famille' => $user->last_name, 'E-mail' => $user->email,
                'Téléphone' => $user->phone, 'Pays' => $user->country?->name_fr,
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
        <flux:heading level="2">Rôles SaaS</flux:heading>
        @foreach ($user->roles as $role)
            <div class="flex flex-col gap-3">
                <flux:text>{{ $role->label }} — attribué le {{ $role->pivot->assigned_at->format('d/m/Y H:i') }}</flux:text>
                @can('renewStaffAssignments', $user)
                    <form method="POST" action="{{ route('central.staff.roles.renew', [$user, $role]) }}" class="flex flex-col gap-3">
                        @csrf
                        <input type="hidden" name="expected_assigned_at" value="{{ $role->pivot->assigned_at->toIso8601String() }}">
                        <flux:textarea name="reason" label="Motif du renouvellement" required />
                        <flux:button type="submit">Renouveler ce rôle</flux:button>
                    </form>
                @endcan
            </div>
        @endforeach
        <div class="flex flex-wrap gap-3">@include('central.pages.admin.staff.actions')</div>
        <flux:button :href="route('central.staff.index')">Retour au personnel</flux:button>
    </section>
</x-layouts::app>
