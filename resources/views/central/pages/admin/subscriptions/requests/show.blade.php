@php
    $item = $subscriptionRequest;
    $cash = $item->payment_choice === \App\Enums\Central\Subscriptions\PaymentChoiceEnum::CASH;
    $prefix = $ownerMode ? 'owner' : 'central';
    $issued = $item->invoice?->status === \App\Enums\Central\Billing\DocumentStatusEnum::ISSUED;
@endphp
<x-layouts::app title="Demande d’abonnement">
    <section class="mx-auto flex w-full max-w-4xl flex-col gap-6">
        <flux:heading size="xl" level="1">Demande d’abonnement — {{ $item->subscription?->plan->name }}</flux:heading>
        @if (session('status'))
            <flux:callout :variant="session('status_variant')" role="status" aria-live="polite">{{ session('status') }}</flux:callout>
        @endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <dl class="grid gap-4 sm:grid-cols-2">
            <div><dt>Référence de demande</dt><dd class="break-all">{{ $item->uuid }}</dd></div>
            <div><dt>Date de soumission</dt><dd>{{ $item->submitted_at?->format('d/m/Y H:i') }}</dd></div>
            <div><dt>Commerçant</dt><dd>{{ $item->owner?->name }}</dd></div>
            <div><dt>E-mail</dt><dd>{{ $item->owner?->email }}</dd></div>
            <div><dt>État</dt><dd>{{ $item->status->label() }}</dd></div>
            <div><dt>Abonnement actuel</dt><dd>{{ $currentSubscription?->plan?->name ?? 'Aucun abonnement actif' }}</dd></div>
            <div><dt>Nouvelle offre demandée</dt><dd>{{ $item->subscription?->plan?->name }}</dd></div>
            <div><dt>Périodicité</dt><dd>{{ $item->subscription?->period?->label() }}</dd></div>
            <div><dt>Montant TTC</dt><dd>{{ $item->subscription?->agreed_amount }} DZD</dd></div>
            <div><dt>Moyen choisi</dt><dd>{{ $item->payment_choice->label() }}</dd></div>
            <div><dt>Période</dt><dd>{{ $item->subscription?->period_starts_at?->format('d/m/Y') }} — {{ $item->subscription?->period_ends_at?->format('d/m/Y') }}</dd></div>
            @if ($item->subscription?->effective_starts_at)
                <div><dt>Période d’accès après démarrage anticipé</dt><dd>{{ $item->subscription->effectiveStartsAt()->format('d/m/Y') }} — {{ $item->subscription->effectiveEndsAt()?->format('d/m/Y') }}</dd></div>
            @endif
            <div><dt>Statut de l’abonnement demandé</dt><dd>{{ $item->subscription?->status->label() }}</dd></div>
            @if ($item->decided_at)
                <div><dt>Décision administrative</dt><dd>{{ $item->decider?->name }} — {{ $item->decided_at->format('d/m/Y H:i') }}</dd></div>
            @endif
        </dl>
        @if ($ownerMode && $item->subscription)
            @can('startNow', $item->subscription)
                <flux:button :href="route('owner.subscriptions.show', $item->subscription).'#demarrer'">Appliquer ce plan maintenant</flux:button>
            @endcan
        @endif
        @if ($item->getAttribute('decision_reason'))<flux:callout heading="Motif du refus">{{ $item->getAttribute('decision_reason') }}</flux:callout>@endif
        @if ($issued)
            <flux:text>Facture {{ $item->invoice->number }}. Elle constate le montant dû ; elle ne prouve pas un paiement.</flux:text>
            @can($ownerMode ? 'viewOwnInvoice' : 'view', $item->invoice)
                <flux:button :href="route($prefix.'.invoices.show', $item->invoice)">Consulter la facture</flux:button>
            @endcan
            @can('download', $item->invoice)
                <flux:button :href="\Illuminate\Support\Facades\URL::temporarySignedRoute($prefix.'.invoices.download', now()->addMinutes(10), ['invoice' => $item->invoice, 'inline' => 1])" target="_blank">Ouvrir le PDF de facture pour télécharger ou imprimer</flux:button>
            @endcan
        @else
            <flux:callout>{{ $item->getAttribute('invoice_error') ?? 'Préparation interne sans numéro ni PDF définitif. La facture et le reçu suivront la confirmation du paiement intégral.' }}</flux:callout>
        @endif
        @if ($item->getAttribute('submitted_payment') !== null)
            @can('viewInitialProof', $item)
                @php($proofPreview = \Illuminate\Support\Facades\URL::temporarySignedRoute($prefix.'.subscription-requests.initial-proof', now()->addMinutes(10), ['subscriptionRequest' => $item, 'inline' => 1]))
                <section class="flex flex-col gap-3" aria-label="Justificatif initial">
                    <flux:heading>Justificatif initial</flux:heading>
                    <iframe src="{{ $proofPreview }}" title="Consulter le justificatif initial" class="h-[36rem] w-full rounded-lg border border-zinc-200 dark:border-zinc-700"></iframe>
                    <div class="flex flex-wrap gap-2">
                        <flux:button :href="$proofPreview" target="_blank" rel="noopener">Ouvrir le justificatif</flux:button>
                        <flux:button :href="\Illuminate\Support\Facades\URL::temporarySignedRoute($prefix.'.subscription-requests.initial-proof', now()->addMinutes(10), ['subscriptionRequest' => $item])">Télécharger le justificatif initial</flux:button>
                    </div>
                </section>
            @endcan
        @endif
        @if ($item->payment)
            <flux:text>Paiement : {{ $item->payment->statusLabel() }}. Une photo seule ne prouve pas la réception des fonds.</flux:text>
            @if ($item->payment->hasReportedFunds() && ! $item->status->isFinal())
                <flux:callout>Une réception de fonds est signalée. La demande ne peut pas être corrigée ou annulée commercialement ; l’administration doit terminer le contrôle ou le rapprochement.</flux:callout>
            @endif
            @can('view', $item->payment)
                <flux:button :href="route($prefix.'.invoices.show', $item->payment->invoice).'#payment-'.$item->payment->uuid">Consulter le paiement et son justificatif</flux:button>
                @if ($item->payment->getAttribute('proof_media_id'))
                    <flux:button :href="\Illuminate\Support\Facades\URL::temporarySignedRoute($prefix.'.payments.proof', now()->addMinutes(10), ['payment' => $item->payment])">Télécharger le justificatif</flux:button>
                @endif
            @endcan
            @can('receipt', $item->payment)
                <flux:button :href="\Illuminate\Support\Facades\URL::temporarySignedRoute($prefix.'.payments.receipt', now()->addMinutes(10), ['payment' => $item->payment, 'inline' => 1])" target="_blank">Ouvrir le reçu pour télécharger ou imprimer</flux:button>
            @endcan
        @endif
        @if ($ownerMode && ! $item->status->isFinal())
            @can('update', $item)
                <flux:heading id="modifier">Modifier ou annuler ma demande</flux:heading>
                @if ($issued)
                    <flux:callout>Avant de modifier ou annuler cette demande, l’administration doit corriger la facture émise. Les documents restent conservés.</flux:callout>
                @endif
                <form method="POST" action="{{ route('owner.subscription-requests.revise', $item) }}" class="flex flex-col gap-3">@csrf @method('PUT')
                    <input type="hidden" name="expected_state" value="{{ $item->managementState() }}">
                    <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    @php($paidPlans = array_values(array_filter($plans, fn ($plan) => $plan['code'] !== 'free' && ! $plan['is_default'])))
                    <div x-data="{ plans: @js($paidPlans), plan: @js($item->subscription->plan->uuid), period: @js((string) $item->subscription->period->value), mode: @js((string) $item->subscription->transition_mode->value) }" class="flex flex-col gap-3">
                        <flux:select name="plan_uuid" label="Nouvelle offre" x-model="plan" required>
                            @foreach ($paidPlans as $plan)<option value="{{ $plan['uuid'] }}">{{ $plan['name'] }} — {{ $plan['monthly_price'] }} DZD/mois, {{ $plan['annual_price'] }} DZD/an</option>@endforeach
                        </flux:select>
                        <flux:select name="period" label="Période" x-model="period" required><option value="1">Mensuelle</option><option value="2">Annuelle</option></flux:select>
                        <input type="hidden" name="expected_amount" x-bind:value="plans.find(p => p.uuid === plan)?.[period === '1' ? 'monthly_price' : 'annual_price'] ?? ''">
                    <input type="hidden" name="payment_choice" value="{{ $item->payment_choice->value }}">
                    <flux:select name="transition_mode" label="Début de la nouvelle période" x-model="mode">
                        @foreach (\App\Enums\Central\Subscriptions\TransitionModeEnum::cases() as $mode)<option value="{{ $mode->value }}" @selected($item->subscription->transition_mode === $mode)>{{ $mode->label() }}</option>@endforeach
                    </flux:select>
                    @if ($currentSubscription && ! $currentSubscription->isFree() && $currentSubscription->status === \App\Enums\Central\Subscriptions\StatusEnum::ACTIVE)
                        <input type="hidden" name="source_uuid" value="{{ $currentSubscription->uuid }}">
                        <input type="hidden" name="expected_source_state" value="{{ $currentSubscription->transitionState() }}">
                    @endif
                    <div x-show="mode === '1'" x-cloak class="flex flex-col gap-3">
                        @include('central.pages.owner.billing.subscriptions.current-plan-warning')
                        <flux:heading>Nouveau plan : <span x-text="plans.find(p => p.uuid === plan)?.name"></span></flux:heading>
                        <ul class="list-inside list-disc text-sm">
                            <template x-for="feature in (plans.find(p => p.uuid === plan)?.features ?? [])" :key="feature.name">
                                <li><span x-text="feature.name"></span> : <span x-text="feature.value"></span></li>
                            </template>
                        </ul>
                        <flux:text>Les nouveaux droits commenceront après confirmation du paiement intégral, pour une période complète. Aucun prorata ni remboursement automatique.</flux:text>
                        <label class="flex items-start gap-2"><input type="checkbox" name="confirm_replacement" value="1" x-bind:required="mode === '1'" x-bind:disabled="mode !== '1'">Je confirme l’arrêt des droits actuels lors de l’activation de la nouvelle période.</label>
                    </div>
                    <flux:button type="submit">Modifier l’offre ou la période</flux:button>
                    </div>
                </form>
                <form method="POST" action="{{ route('owner.subscription-requests.cancel', $item) }}">@csrf
                    <input type="hidden" name="expected_state" value="{{ $item->managementState() }}">
                    <flux:button type="submit">Annuler la demande non payée</flux:button>
                </form>
                <form method="POST" action="{{ route('owner.subscription-requests.choice', $item) }}" class="flex flex-col gap-3">@csrf @method('PATCH')
                    <input type="hidden" name="expected_state" value="{{ $item->managementState() }}">
                    <flux:select name="payment_choice" label="Modifier mon moyen de paiement">
                        @foreach (\App\Enums\Central\Subscriptions\PaymentChoiceEnum::cases() as $choice)<option value="{{ $choice->value }}" @selected($item->payment_choice === $choice)>{{ $choice->label() }}</option>@endforeach
                    </flux:select>
                    <flux:button type="submit">Enregistrer mon choix</flux:button>
                </form>
                @if ($cash)<flux:callout>Présentez votre référence de demande au bureau Aydra. Seul l’administrateur peut constater les espèces réellement reçues. Aucun droit ne sera activé avant ce contrôle. {{ config('central.billing.cash_instructions') }}</flux:callout>
                @elseif ($item->invoice !== null && $item->subscription->status === \App\Enums\Central\Subscriptions\StatusEnum::SCHEDULED)
                    <flux:callout>{{ config('central.billing.payment_instructions') ?: 'Demandez les coordonnées officielles de versement à l’administration avant tout envoi d’argent.' }}</flux:callout>
                    <form method="POST" enctype="multipart/form-data" action="{{ route('owner.subscription-requests.proof', $item) }}" class="flex flex-col gap-3">@csrf
                        <input type="hidden" name="expected_state" value="{{ $item->managementState() }}"><input type="hidden" name="currency" value="DZD">
                        <input type="hidden" name="operation_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <flux:select name="method" label="Réseau du versement externe" required>
                            @foreach ([\App\Enums\Central\Billing\TransferMethodEnum::BANK_TRANSFER, \App\Enums\Central\Billing\TransferMethodEnum::CCP_TRANSFER, \App\Enums\Central\Billing\TransferMethodEnum::BARIDIMOB] as $method)<option value="{{ $method->value }}">{{ $method->label() }}</option>@endforeach
                        </flux:select>
                        <flux:input name="amount" label="Montant réellement versé (DZD)" :value="$item->subscription->agreed_amount" required />
                        <flux:input type="datetime-local" name="occurred_at" label="Date du versement" required />
                        <flux:input name="reference" label="Référence du versement (si disponible)" maxlength="255" />
                        <flux:input type="file" name="proof" label="Photo ou PDF du justificatif — 8 Mo maximum" accept="application/pdf,image/jpeg,image/png" required />
                        <flux:button type="submit">Soumettre le justificatif pour vérification</flux:button>
                    </form>
                @endif
            @endcan
        @elseif (! $ownerMode && ! $item->status->isFinal())
            @if ($item->payment === null || $item->payment->getAttribute('transfer_status') !== \App\Enums\Central\Billing\TransferStatusEnum::UNCERTAIN)
                @can('accept', $item)
                    @if ($cash || $item->payment || $item->getAttribute('submitted_payment') !== null)
                        <form method="POST" action="{{ route('central.subscription-requests.accept', $item) }}" class="flex flex-col gap-3">@csrf
                            <input type="hidden" name="expected_state" value="{{ $item->managementState() }}">
                            <input type="hidden" name="expected_payment_state" value="{{ $item->payment?->managementState() }}">
                            <flux:text>En acceptant, vous confirmez avoir reçu et contrôlé le montant intégral de {{ $item->subscription->agreed_amount }} DZD. Le montant et la date de confirmation sont renseignés automatiquement.</flux:text>
                            @if (filled($item->getAttribute('invoice_error')))
                                <flux:checkbox name="acknowledged" value="1" label="J’ai lu et approuvé ce message." description="Cette confirmation ne remplace pas les vérifications fiscales et techniques nécessaires à la facture." required />
                            @endif
                            <flux:button type="submit" variant="primary">Accepter la demande</flux:button>
                        </form>
                    @else
                        <flux:text>Le justificatif du versement doit être fourni avant de pouvoir accepter cette demande.</flux:text>
                    @endif
                @endcan
            @endif
            @can('reject', $item)
                <flux:modal.trigger :name="'reject-subscription-request-'.$item->uuid">
                    <flux:button variant="danger">Refuser</flux:button>
                </flux:modal.trigger>
                <flux:modal :name="'reject-subscription-request-'.$item->uuid" class="md:w-96">
                    <form method="POST" action="{{ route('central.subscription-requests.reject', $item) }}" class="flex flex-col gap-4">
                        @csrf
                        <flux:heading size="lg">Refuser la demande</flux:heading>
                        <input type="hidden" name="expected_state" value="{{ $item->managementState() }}">
                        <input type="hidden" name="expected_payment_state" value="{{ $item->payment?->managementState() }}">
                        <flux:textarea name="reason" label="Motif du refus, visible par le commerçant" required maxlength="2000">{{ is_string(old('reason')) ? old('reason') : '' }}</flux:textarea>
                        <flux:button type="submit" variant="danger">Refuser avec motif</flux:button>
                    </form>
                </flux:modal>
            @endcan
        @endif
        @if ($history !== null)
            <flux:heading>Historique des décisions et corrections</flux:heading>
            @foreach ($history as $event)
                <flux:text>{{ $event->created_at->format('d/m/Y H:i') }} — {{ $event->causer?->name ?? 'Système' }} — {{ $event->description }}</flux:text>
                @if (is_string($event->getProperty('reason')) && $event->getProperty('reason') !== '')<flux:text>Motif conservé : {{ $event->getProperty('reason') }}</flux:text>@endif
            @endforeach
            {{ $history->links() }}
        @endif
        <flux:button :href="route($prefix.'.subscription-requests.index')">Retour aux demandes</flux:button>
    </section>
</x-layouts::app>
