<x-layouts::app :title="__('Abonnement')">
    <section class="flex flex-col gap-6">
        <flux:heading size="xl" level="1">Détail de l’abonnement</flux:heading>
        @if (session('status'))<flux:text role="status">{{ session('status') }}</flux:text>@endif
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
        <div><flux:button :href="route('central.subscriptions.index')">Retour aux abonnements</flux:button></div>
    </section>
</x-layouts::app>
