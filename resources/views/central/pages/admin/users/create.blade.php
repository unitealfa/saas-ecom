<x-layouts::app :title="__('Créer un compte')">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="space-y-2">
            <h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Créer un compte central</h1>
            <p class="aydra-muted max-w-2xl text-sm sm:text-base">Renseignez les informations de la personne à inviter sur Aydra.</p>
        </header>
        <flux:callout>Le destinataire recevra une invitation pour choisir son mot de passe et confirmer son adresse e-mail.</flux:callout>
        @if ($errors->any())
            <flux:callout variant="danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </flux:callout>
        @endif
        <form method="POST" action="{{ route('central.users.store') }}" class="flex flex-col gap-6"
            x-data="{ kind: @js(old('account_kind', $canCreateMerchant ? 'merchant' : 'staff')) }">
            @csrf
            <section class="aydra-users-panel p-5 sm:p-6" aria-labelledby="create-identity">
                <h2 id="create-identity" class="aydra-heading text-lg font-semibold">Identité et coordonnées</h2>
                <p class="aydra-muted mt-1 text-sm">Choisissez le type de compte, puis indiquez les coordonnées de son titulaire.</p>
                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <flux:select name="account_kind" label="Type de compte" x-model="kind">
                @if ($canCreateMerchant)<option value="merchant">Commerçant</option>@endif
                @if ($canCreateStaff)<option value="staff">Personnel SaaS</option>@endif
                    </flux:select>
                    <div class="hidden sm:block" aria-hidden="true"></div>
                    <flux:input name="first_name" label="Prénom" :value="old('first_name')" autocomplete="given-name" />
                    <flux:input name="last_name" label="Nom de famille" :value="old('last_name')" autocomplete="family-name" required />
                    <flux:input name="email" type="email" label="Adresse e-mail" :value="old('email')" autocomplete="email" required />
                    <flux:input name="phone" label="Téléphone" type="tel" :value="old('phone')" autocomplete="tel" />
                    <flux:select name="phone_country_uuid" label="Pays et indicatif du téléphone">
                <option value="">Même pays que le profil</option>
                @foreach ($countries as $country)
                    <option value="{{ $country->uuid }}" @selected(old('phone_country_uuid') === $country->uuid)>{{ $country->name_fr }} ({{ $country->calling_code }})</option>
                @endforeach
                    </flux:select>
                    <flux:select name="country_uuid" label="Pays" required>
                <option value="">Choisir un pays</option>
                @foreach ($countries as $country)
                    <option value="{{ $country->uuid }}" @selected(old('country_uuid') === $country->uuid)>{{ $country->name_fr }}</option>
                @endforeach
                    </flux:select>
                </div>
                @if ($canCreateMerchant)
                    <p x-show="kind === 'merchant'" class="aydra-muted mt-4 text-sm">Un commerçant créé ici est immédiatement approuvé, avec son dossier validé. Il configurera sa boutique après acceptation de l’invitation.</p>
                @endif
            </section>
            @if ($canCreateMerchant)
                <div x-show="kind === 'merchant'" class="aydra-users-panel p-5 sm:p-6">
                    <fieldset x-bind:disabled="kind !== 'merchant'" class="flex flex-col gap-5">
                        @include('central.pages.admin.users.professional-fields')
                    </fieldset>
                </div>
            @endif
            @if ($canCreateStaff)
                <div x-show="kind === 'staff'" class="aydra-users-panel p-5 sm:p-6">
                <fieldset class="flex flex-col gap-3">
                    <legend class="mb-3 font-medium">Rôles du personnel</legend>
                    <flux:text>Seuls les rôles contenant des droits que vous possédez encore sont proposés. Les rôles sélectionnés ne doivent partager aucune permission.</flux:text>
                    @forelse ($roles as $role)
                        <flux:checkbox name="roles[]" :value="$role->uuid" :label="$role->label"
                            :checked="in_array($role->uuid, old('roles', []), true)" x-bind:disabled="kind !== 'staff'" />
                    @empty
                        <flux:text>Aucun rôle attribuable. Le Super Admin doit préparer un rôle adapté.</flux:text>
                    @endforelse
                </fieldset>
                </div>
            @endif
            <div class="flex flex-wrap items-center gap-3">
                <flux:button variant="primary" type="submit">Créer et inviter</flux:button>
                @can('viewAny', \App\Models\User::class)
                    <flux:button :href="route('central.users.index')">Retour aux utilisateurs</flux:button>
                @else
                    <flux:button :href="route('dashboard')">Retour au dashboard</flux:button>
                @endcan
            </div>
        </form>
    </section>
</x-layouts::app>
