<x-layouts::app :title="__('Abonnement')">
    <section class="mx-auto flex w-full max-w-screen-xl flex-col gap-6">
        <header class="flex flex-wrap items-start justify-between gap-5"><div class="space-y-2"><h1 class="aydra-heading text-3xl font-semibold tracking-tight sm:text-4xl">Détail de l’abonnement</h1><p class="aydra-muted text-sm sm:text-base">Consultez les informations et les actions disponibles pour cet abonnement.</p></div><flux:button :href="route('central.subscriptions.index')" icon="arrow-left">Retour aux abonnements</flux:button></header>
        @if ($subscription->request)
            @can('viewAny', \App\Models\Central\Subscriptions\SubscriptionRequest::class)
                <flux:button class="self-start" :href="route('central.subscription-requests.show', $subscription->request)">Consulter la demande et son paiement</flux:button>
            @endcan
        @endif
        @if (session('status'))<flux:callout role="status">{{ session('status') }}</flux:callout>@endif
        @if ($errors->any())<flux:callout variant="danger" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</flux:callout>@endif
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading mb-5 text-lg font-semibold">Informations de l’abonnement</h2>
        <dl class="aydra-detail-grid grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
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
                'Début de période' => $subscription->effectiveStartsAt()?->format('d/m/Y H:i'),
                'Fin de période' => $subscription->effectiveEndsAt()?->format('d/m/Y H:i') ?? 'Sans échéance',
                'Fin de l’essai' => $subscription->trial_ends_at?->format('d/m/Y H:i'),
                'Fin de l’abonnement' => $subscription->ended_at?->format('d/m/Y H:i'),
                'Renouvellement automatique' => $subscription->auto_renew === null ? null : ($subscription->auto_renew ? 'Oui' : 'Non'),
                'Attribué par' => $subscription->assignedBy?->name,
            ] as $label => $value)
                <div><dt class="font-medium">{{ $label }}</dt><dd><flux:text>{{ $value ?? 'Non renseigné' }}</flux:text></dd></div>
            @endforeach
        </dl>
        </section>
        @if ($subscription->status === \App\Enums\Central\Subscriptions\StatusEnum::SCHEDULED)
            <flux:callout>Cette période est réservée. La facture définitive et le reçu sont produits après contrôle des fonds ; les droits attendent la finalisation et la date de début.</flux:callout>
        @endif
        <section class="aydra-users-panel p-5 sm:p-6"><h2 class="aydra-heading mb-4 text-lg font-semibold">Actions</h2><div class="flex flex-wrap gap-3">
            @php($invoice = $subscription->request?->invoice)
            @if ($invoice?->subscription_id === $subscription->id && $invoice->status === \App\Enums\Central\Billing\DocumentStatusEnum::ISSUED)
                @can('viewBillingDossier', $invoice)
                    <flux:button :href="route('central.invoices.show', $invoice)">Consulter la facture</flux:button>
                @endcan
            @endif
            @if ($subscription->request)
                @can('view', $subscription->request)<flux:button :href="route('central.subscription-requests.show', $subscription->request)">Demande et facturation</flux:button>@endcan
            @endif
            @can('history', $subscription)
                <flux:button :href="route('central.subscriptions.history', $subscription)">Historique des abonnements</flux:button>
            @endcan
            @if (in_array($subscription->status, [\App\Enums\Central\Subscriptions\StatusEnum::ACTIVE, \App\Enums\Central\Subscriptions\StatusEnum::TRIAL], true) && ! $subscription->effectiveStartsAt()->isFuture() && ! $subscription->isFree() && ($subscription->effectiveEndsAt() === null || $subscription->effectiveEndsAt()->isFuture()))
                @can('deactivate', $subscription)
                    <form method="POST" action="{{ route('central.subscriptions.deactivate', $subscription) }}" x-data x-on:submit="if (! window.confirm('Désactiver immédiatement cet abonnement et appliquer l’offre gratuite ? Le quota des boutiques sera recalculé.')) $event.preventDefault()">
                        @csrf
                        <input type="hidden" name="expected_state" value="{{ $subscription->transitionState() }}">
                        <flux:button type="submit">Désactiver immédiatement</flux:button>
                    </form>
                @endcan
            @endif
            @if ($subscription->status === \App\Enums\Central\Subscriptions\StatusEnum::ACTIVE && ! $subscription->effectiveStartsAt()->isFuture() && $subscription->ended_at === null && ($subscription->isFree() || $subscription->effectiveEndsAt()?->isFuture()))
                @can('change', $subscription)
                    @can('saas.payments.validate')
                    <flux:button :href="route('central.subscriptions.change.form', $subscription)">Changer l’offre</flux:button>
                    @endcan
                @endcan
            @endif
            @if ($subscription->status === \App\Enums\Central\Subscriptions\StatusEnum::ACTIVE && $subscription->effectiveEndsAt()?->isFuture() && $subscription->ended_at === null)
                @can('close', $subscription)
                    <form method="POST" action="{{ route('central.subscriptions.close', $subscription) }}" x-data x-on:submit="if (! window.confirm('Mettre fin à cet abonnement à la fin de sa période ?')) $event.preventDefault()">
                        @csrf
                        <input type="hidden" name="expected_state" value="{{ $subscription->transitionState() }}">
                        <flux:button type="submit">Programmer la fin</flux:button>
                    </form>
                @endcan
            @endif
            <flux:button :href="route('central.subscriptions.index')">Retour aux abonnements</flux:button>
        </div></section>
    </section>
</x-layouts::app>
