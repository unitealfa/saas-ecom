<x-layouts::auth title="Votre inscription Aydra">
    @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
    <div class="space-y-6"
        x-data="{
            version: @js($progress['version']),
            state: @js($progress['state']),
            shops: @js($progress['shops']),
            endpoint: @js(route('registration.status')),
            loginUrl: @js(route('login')),
            timer: null,
            cooldownTimer: null,
            loading: false,
            coolingDown: false,
            refreshLimited: false,
            refreshFailed: false,
            visibilityHandler: null,
            init() {
                this.timer = window.setInterval(() => this.refresh(), this.state === 'preparing' ? 5000 : 15000);
                this.visibilityHandler = () => { if (!document.hidden) this.refresh(); };
                document.addEventListener('visibilitychange', this.visibilityHandler);
                this.refresh();
            },
            destroy() {
                window.clearInterval(this.timer);
                window.clearTimeout(this.cooldownTimer);
                document.removeEventListener('visibilitychange', this.visibilityHandler);
            },
            async refresh() {
                if (document.hidden || this.loading || this.coolingDown) return;
                this.loading = true;
                this.coolingDown = true;
                let cooldown = 5000;
                try {
                    const response = await fetch(this.endpoint, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                        cache: 'no-store',
                        signal: AbortSignal.timeout(10000),
                    });
                    if (response.status === 401 || response.status === 403) {
                        window.location.replace(this.loginUrl);
                        return;
                    }
                    if (response.status === 429) {
                        this.refreshLimited = true;
                        cooldown = Math.max(cooldown, (Number(response.headers.get('Retry-After')) || 5) * 1000);
                        return;
                    }
                    if (!response.ok) throw new Error('Status refresh failed');
                    const progress = await response.json();
                    this.refreshFailed = false;
                    this.refreshLimited = false;
                    if (progress.state !== this.state || progress.state === 'ready'
                        || (this.state !== 'preparing' && progress.version !== this.version)) {
                        window.location.replace(progress.redirect);
                        return;
                    }
                    this.shops = progress.shops ?? this.shops;
                    this.version = progress.version;
                } catch {
                    this.refreshFailed = true;
                } finally {
                    this.loading = false;
                    this.cooldownTimer = window.setTimeout(() => {
                        this.coolingDown = false;
                        this.refreshLimited = false;
                    }, cooldown);
                }
            },
        }">
        <flux:heading size="xl">Bonjour {{ $user->first_name ?: $user->last_name }}</flux:heading>
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
            <flux:text>Le suivi s’actualise automatiquement toutes les 5 secondes. Vous pouvez rester connecté sur cette page.</flux:text>
            <template x-for="(shop, index) in shops" :key="index">
                <div class="space-y-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" aria-live="polite">
                    <p class="font-medium" x-text="shop.name"></p>
                    <p class="text-sm" x-text="'État : ' + shop.status"></p>
                    <p class="text-sm" x-text="shop.preparation"></p>
                    <div x-show="shop.in_progress" role="status" aria-label="Préparation en cours">
                        <flux:icon.loading class="size-5 animate-spin" />
                    </div>
                    <p x-show="shop.failed" class="text-sm">Vos informations sont conservées. Une reprise peut être demandée.</p>
                    <template x-if="shop.retry_url">
                        <form method="POST" :action="shop.retry_url">@csrf <flux:button type="submit">Relancer la préparation</flux:button></form>
                    </template>
                </div>
            </template>
        @else
            <flux:heading>Bienvenue sur Aydra !</flux:heading>
            <flux:text>Votre compte a bien été créé.</flux:text>
            <flux:text>Votre dossier est actuellement en attente de validation par notre équipe.</flux:text>
            <flux:text>Vous ne pouvez pas encore accéder à votre espace commerçant. Après approbation, vous pourrez créer votre première boutique.</flux:text>
        @endif
        <p x-cloak x-show="refreshFailed" role="status" class="text-sm text-zinc-600 dark:text-zinc-400">L’actualisation automatique est momentanément indisponible. Vous pouvez utiliser le bouton ci-dessous.</p>
        <p x-cloak x-show="refreshLimited" role="status" class="text-sm text-zinc-600 dark:text-zinc-400">Veuillez patienter avant de réactualiser votre statut.</p>
        <flux:button type="button" x-on:click="refresh()" x-bind:disabled="loading || coolingDown" x-bind:aria-busy="loading">Actualiser mon statut</flux:button>
        <form method="POST" action="{{ route('logout') }}">@csrf <flux:button type="submit" variant="ghost">Se déconnecter</flux:button></form>
    </div>
</x-layouts::auth>
