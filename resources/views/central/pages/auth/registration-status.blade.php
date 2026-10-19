<x-layouts::auth title="Votre inscription Aydra">
    @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
    <div class="space-y-6"
        x-data="{
            version: @js($progress['version']),
            endpoint: @js(route('registration.status')),
            loginUrl: @js(route('login')),
            timer: null,
            loading: false,
            refreshFailed: false,
            init() {
                this.timer = window.setInterval(() => this.refresh(), 15000);
            },
            destroy() {
                window.clearInterval(this.timer);
            },
            async refresh() {
                if (document.hidden || this.loading) return;
                this.loading = true;
                try {
                    const response = await fetch(this.endpoint, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                        cache: 'no-store',
                    });
                    if (response.status === 401 || response.status === 403) {
                        window.location.replace(this.loginUrl);
                        return;
                    }
                    if (!response.ok) throw new Error('Status refresh failed');
                    const progress = await response.json();
                    this.refreshFailed = false;
                    if (progress.redirect !== this.endpoint || progress.version !== this.version) {
                        window.location.replace(progress.redirect);
                    }
                } catch {
                    this.refreshFailed = true;
                } finally {
                    this.loading = false;
                }
            },
        }">
        <flux:heading size="xl">Bonjour {{ $user->first_name ?: $user->last_name }}</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @if ($progress['state'] === 'rejected')
            <flux:heading>Inscription refusée</flux:heading>
            <flux:text>Votre demande d’inscription n’a pas été approuvée.</flux:text>
            @if ($progress['reason'])
                <flux:callout variant="danger">
                    <flux:callout.heading>Motif du refus</flux:callout.heading>
                    <flux:callout.text class="whitespace-pre-wrap">{{ $progress['reason'] }}</flux:callout.text>
                </flux:callout>
            @else
                <flux:text>Aucun motif n’a été renseigné.</flux:text>
            @endif
            <flux:text>Notre équipe peut réexaminer votre inscription. Cette page vous dirigera automatiquement vers la prochaine étape si votre compte est approuvé.</flux:text>
        @elseif ($progress['state'] === 'preparing')
            <flux:heading>Préparation de votre boutique</flux:heading>
            <flux:text>Votre boutique a été enregistrée. Vous accéderez automatiquement au tableau de bord lorsqu’une de vos boutiques sera prête et active.</flux:text>
            @foreach ($progress['shops'] as $shop)
                <flux:callout>
                    <flux:callout.heading>{{ $shop['name'] }}</flux:callout.heading>
                    <flux:text>État : {{ $shop['status'] }}</flux:text>
                    @if ($shop['failed'])
                        <flux:callout.text>La préparation a échoué. Vos informations sont conservées et vous n’avez pas besoin de créer une nouvelle boutique.</flux:callout.text>
                        @if ($shop['retry_url'])
                            <form method="POST" action="{{ $shop['retry_url'] }}">@csrf <flux:button type="submit">Relancer la préparation</flux:button></form>
                        @endif
                    @elseif (! $shop['prepared'])
                        <flux:callout.text>La préparation technique est en cours. Votre boutique n’est pas encore accessible.</flux:callout.text>
                    @else
                        <flux:callout.text>La préparation est terminée. L’accès reste limité par l’état du compte, du quota ou de la boutique. Aucune seconde approbation de boutique n’est demandée.</flux:callout.text>
                    @endif
                </flux:callout>
            @endforeach
        @else
            <flux:heading>Bienvenue sur Aydra !</flux:heading>
            <flux:text>Votre compte a bien été créé.</flux:text>
            <flux:text>Votre dossier est actuellement en attente de validation par notre équipe.</flux:text>
            <flux:text>Vous ne pouvez pas encore accéder à votre espace commerçant. Après approbation, vous pourrez créer votre première boutique.</flux:text>
        @endif
        <flux:text>Cette page vérifie automatiquement votre situation toutes les 15 secondes lorsqu’elle est visible.</flux:text>
        <p x-cloak x-show="refreshFailed" role="status" class="text-sm text-zinc-600 dark:text-zinc-400">L’actualisation automatique est momentanément indisponible. Vous pouvez utiliser le bouton ci-dessous.</p>
        <flux:button :href="route('registration.status')">Actualiser mon statut</flux:button>
        <form method="POST" action="{{ route('logout') }}">@csrf <flux:button type="submit" variant="ghost">Se déconnecter</flux:button></form>
    </div>
</x-layouts::auth>
