<x-layouts::app :title="__('Modifier le compte')">
    <section class="mx-auto flex w-full max-w-screen-lg flex-col gap-6">
        <header class="space-y-2">
            <h1 class="aydra-heading break-words text-3xl font-semibold tracking-tight sm:text-4xl">Modifier {{ $user->name }}</h1>
            <p class="aydra-muted max-w-2xl text-sm sm:text-base">Mettez à jour les informations de ce compte.</p>
        </header>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        <flux:callout>Ces modifications conservent la décision d’inscription, y compris pour les informations professionnelles. Une nouvelle adresse e-mail doit être vérifiée. Le mot de passe et les rôles ne sont pas modifiés par ce formulaire.</flux:callout>
        @if ($errors->any())
            <flux:callout variant="danger" role="alert">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </flux:callout>
        @endif
        <form method="POST" action="{{ route('central.users.update', $user) }}" class="flex flex-col gap-6">
            @csrf
            @method('PUT')
            <section class="aydra-users-panel p-5 sm:p-6" aria-labelledby="edit-identity">
                <h2 id="edit-identity" class="aydra-heading text-lg font-semibold">Identité et coordonnées</h2>
                <p class="aydra-muted mt-1 text-sm">Informations utilisées pour identifier et contacter le titulaire du compte.</p>
                <div class="mt-6 grid gap-5 sm:grid-cols-2">
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
                    <flux:select name="country_uuid" label="Pays" required>
                @foreach ($countries as $country)
                    <option value="{{ $country->uuid }}" @selected(old('country_uuid', $user->country->uuid) === $country->uuid)>{{ $country->name_fr }}</option>
                @endforeach
                    </flux:select>
                    <flux:input name="locale" label="Langue du compte (fr, en, ar…)" :value="old('locale', $user->getAttribute('locale'))" required />
                </div>
                <p class="aydra-muted mt-4 text-sm">Changer le pays du profil ne transforme pas le téléphone existant.</p>
            </section>
            @can('viewLegal', $user)
                <section class="aydra-users-panel flex flex-col gap-5 p-5 sm:p-6" aria-label="Informations professionnelles">
                    @include('central.pages.admin.users.professional-fields', ['profile' => $user])
                </section>
            @endcan
            <div class="flex flex-wrap items-center gap-3">
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
                <form method="POST" action="{{ route('central.tenants.priorities', $ownerShops->first()) }}" class="aydra-users-panel flex flex-col gap-4 p-5 sm:p-6">
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
