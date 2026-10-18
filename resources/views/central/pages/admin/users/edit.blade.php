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
            <flux:input name="locale" label="Langue du compte (fr, en, ar…)" :value="old('locale', $user->getAttribute('locale'))" required />
            @can('viewLegal', $user)
                <flux:heading level="2">Informations professionnelles</flux:heading>
                <flux:text>Toute modification du dossier vendeur demande une nouvelle vérification. Les documents historiques conservent leur identité d’origine.</flux:text>
                @foreach ([
                    'legal_form' => 'Forme juridique', 'activity_nature' => 'Nature de l’activité',
                    'nif' => 'NIF', 'nis' => 'NIS', 'registration_number' => 'Registre de commerce',
                    'artisan_card_number' => 'Carte artisan',
                ] as $field => $label)
                    <flux:input :name="$field" :label="$label" :value="old($field, $user->getAttribute($field))" />
                @endforeach
                <flux:textarea name="legal_address" label="Adresse professionnelle">{{ old('legal_address', $user->getAttribute('legal_address')) }}</flux:textarea>
                <flux:input type="number" name="share_capital" label="Capital social (facultatif)" min="0" step="0.01" :value="old('share_capital', $user->getAttribute('share_capital'))" />
            @endcan
            <flux:text>Les rôles se gèrent avec le bouton Rôles ; l’état du compte avec Suspendre ou Réactiver. Les abonnements et les validations sont consultables sur la fiche et ne sont pas modifiés par ce formulaire.</flux:text>
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
