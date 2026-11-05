<x-layouts::app :title="__('Abonnement')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Détail de l’abonnement</flux:heading>
        @if (session('status'))<flux:text role="status">{{ session('status') }}</flux:text>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <dl class="grid gap-4 sm:grid-cols-2">
            @foreach ([
                'Identifiant' => $subscription->uuid,
                'Propriétaire' => $subscription->owner?->name,
                'E-mail' => $subscription->owner?->email,
                'Offre' => $subscription->plan ? $subscription->plan->name.' — version '.$subscription->plan->version : null,
                'Code de l’offre' => $subscription->plan?->code,
                'État' => $subscription->status?->label(),
                'Périodicité' => $subscription->period?->label(),
                'Montant convenu' => $subscription->agreed_amount !== null ? $subscription->agreed_amount.' DZD' : null,
                'Début' => $subscription->started_at?->format('d/m/Y H:i'),
                'Début de période' => $subscription->period_starts_at?->format('d/m/Y H:i'),
                'Fin de période' => $subscription->period_ends_at?->format('d/m/Y H:i') ?? 'Sans échéance',
                'Fin de l’essai' => $subscription->trial_ends_at?->format('d/m/Y H:i'),
                'Fin de l’abonnement' => $subscription->ended_at?->format('d/m/Y H:i'),
                'Renouvellement automatique' => $subscription->auto_renew === null ? null : ($subscription->auto_renew ? 'Oui' : 'Non'),
                'Attribué par' => $subscription->assignedBy?->name,
            ] as $label => $value)
                <div><dt class="font-medium">{{ $label }}</dt><dd><flux:text>{{ $value ?? 'Non renseigné' }}</flux:text></dd></div>
            @endforeach
        </dl>
        <div class="flex flex-wrap gap-3">
            @can('viewAny', \App\Models\Central\Subscriptions\SubscriptionInstallment::class)
                <flux:button :href="route('central.subscriptions.installments.index', $subscription)">Échéances</flux:button>
            @endcan
            @can('createInstallment', $subscription)
                @can('create', \App\Models\Central\Billing\SaasInvoice::class)
                    <flux:button :href="route('central.invoices.create', $subscription)">Préparer l’échéance et la facture</flux:button>
                @endcan
            @endcan
            @can('history', $subscription)
                <flux:button :href="route('central.subscriptions.history', $subscription)">Historique des abonnements</flux:button>
            @endcan
            @if (in_array($subscription->status, [\App\Enums\Central\Subscriptions\StatusEnum::ACTIVE, \App\Enums\Central\Subscriptions\StatusEnum::TRIAL], true) && ! $subscription->period_starts_at->isFuture() && $subscription->plan?->code !== 'free' && ($subscription->effectiveEndsAt() === null || $subscription->effectiveEndsAt()->isFuture()))
                @can('deactivate', $subscription)
                    <form method="POST" action="{{ route('central.subscriptions.deactivate', $subscription) }}" x-data x-on:submit="if (! window.confirm('Désactiver immédiatement cet abonnement et appliquer l’offre gratuite ? Le quota des boutiques sera recalculé.')) $event.preventDefault()">
                        @csrf
                        <input type="hidden" name="expected_state" value="{{ $subscription->transitionState() }}">
                        <flux:button type="submit">Désactiver immédiatement</flux:button>
                    </form>
                @endcan
            @endif
            @if ($subscription->status === \App\Enums\Central\Subscriptions\StatusEnum::ACTIVE && $subscription->period_ends_at?->isFuture() && $subscription->ended_at === null && $subscription->plan?->code !== 'free')
                @can('change', $subscription)
                    <flux:button :href="route('central.subscriptions.change.form', $subscription)">Changer l’offre</flux:button>
                @endcan
                @can('close', $subscription)
                    <form method="POST" action="{{ route('central.subscriptions.close', $subscription) }}" x-data x-on:submit="if (! window.confirm('Mettre fin à cet abonnement à la fin de sa période ?')) $event.preventDefault()">
                        @csrf
                        <input type="hidden" name="expected_state" value="{{ $subscription->transitionState() }}">
                        <flux:button type="submit">Programmer la fin</flux:button>
                    </form>
                @endcan
            @endif
            <flux:button :href="route('central.subscriptions.index')">Retour aux abonnements</flux:button>
        </div>
    </section>
</x-layouts::app>
