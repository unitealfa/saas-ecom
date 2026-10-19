<x-layouts::app :title="__('Modifier le compte')">
    <section class="mx-auto flex max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">Modifier {{ $user->name }}</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        <flux:text>Un changement d’e-mail demande une nouvelle vérification. Le mot de passe et les rôles ne sont pas modifiés par ce formulaire.</flux:text>
        @if ($errors->any())
            <flux:callout variant="danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </flux:callout>
        @endif
        <flux:button :href="route('central.users.contacts.index', $user)">Gérer les e-mails et téléphones</flux:button>
        <form method="POST" action="{{ route('central.users.update', $user) }}" class="flex flex-col gap-5">
            @csrf
            @method('PUT')
            <flux:input name="first_name" label="Prénom" :value="old('first_name', $user->first_name)" autocomplete="given-name" />
            <flux:input name="last_name" label="Nom de famille" :value="old('last_name', $user->last_name)" autocomplete="family-name" required />
            <flux:input name="email" type="email" label="Adresse e-mail" :value="old('email', $user->email)" autocomplete="email" required />
            <flux:input name="phone" type="tel" label="Téléphone" :value="old('phone', $user->phone)" autocomplete="tel" />
            <flux:select name="phone_country_uuid" label="Pays et indicatif du téléphone">
                <option value="">Conserver le pays du numéro actuel</option>
                @foreach ($phoneCountries as $country)
                    <option value="{{ $country->uuid }}" @selected(old('phone_country_uuid', $user->phoneCountry?->uuid) === $country->uuid)>{{ $country->name_fr }} ({{ $country->calling_code }}){{ $country->is_active ? '' : ' — pays inactif, numéro existant' }}</option>
                @endforeach
            </flux:select>
            <flux:text>Changer le pays du profil ne transforme pas le téléphone existant.</flux:text>
            <flux:select name="country_uuid" label="Pays" required>
                @foreach ($countries as $country)
                    <option value="{{ $country->uuid }}" @selected(old('country_uuid', $user->country->uuid) === $country->uuid)>{{ $country->name_fr }}</option>
                @endforeach
            </flux:select>
            <flux:input name="locale" label="Langue du compte (fr, en, ar…)" :value="old('locale', $user->getAttribute('locale'))" required />
            @can('viewLegal', $user)
                @include('central.pages.admin.users.professional-fields', ['profile' => $user])
            @endcan
            <flux:text>Les rôles se gèrent séparément. Une modification de l’identité ou des informations professionnelles d’un commerçant approuvé demande une nouvelle approbation du compte et du dossier. Suspendre et Réactiver restent des actions distinctes.</flux:text>
            <div class="flex flex-wrap gap-3">
                <flux:button type="submit" variant="primary">Enregistrer</flux:button>
                @can('view', $user)
                    <flux:button :href="route('central.users.show', $user)">Retour à la fiche</flux:button>
                @else
                    <flux:button :href="route('dashboard')">Retour au dashboard</flux:button>
                @endcan
            </div>
        </form>
        @if ($ownerShops->isNotEmpty())
            @can('updatePriorities', $ownerShops->first())
                <form method="POST" action="{{ route('central.tenants.priorities', $ownerShops->first()) }}" class="flex flex-col gap-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    @csrf @method('PUT')
                    <flux:heading level="2">Priorités des boutiques de {{ $user->name }}</flux:heading>
                    <flux:text>Classez toutes ses boutiques de 1 à {{ $ownerShops->count() }}, sans doublon. Les boutiques éligibles sont choisies sous le quota de l’offre ; les suspensions et les préparations ne sont pas annulées.</flux:text>
                    @foreach ($ownerShops as $shop)
                        <flux:input type="number" name="priorities[{{ $shop->uuid }}]" :label="$shop->shop_name.' — '.$shop->status->label()" min="1" :max="$ownerShops->count()"
                            :value="old('priorities.'.$shop->uuid, $shop->activation_priority ?? ($loop->index + 1))" required />
                    @endforeach
                    <flux:input name="reason" label="Motif facultatif" :value="old('reason')" />
                    <flux:button type="submit" variant="primary">Enregistrer les priorités</flux:button>
                </form>
            @endcan
        @endif
    </section>
</x-layouts::app>
