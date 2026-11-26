<x-layouts::app title="Factures SaaS">
    <section class="mx-auto flex w-full max-w-7xl flex-col gap-7">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="aydra-heading text-3xl font-semibold tracking-tight">Factures et paiements</h1>
                <flux:text>Mes échéances, factures, paiements, reçus et corrections documentaires.</flux:text>
            </div>
            <flux:button :href="route('owner.subscriptions.index')">Mes offres et abonnements</flux:button>
        </div>

        @if (session('status'))
            <flux:callout>{{ session('status') }}</flux:callout>
        @endif

        <flux:callout>
            Paiement intégral pour chaque période, mensuelle ou annuelle. Le commerçant règle en espèces auprès
            de l'administration ou effectue un versement hors plateforme puis dépose son justificatif.
            Le dépôt ne signifie pas que le paiement est confirmé : seule l'administration valide la réception
            réelle des fonds. Aucun paiement fractionné, remboursement ou paiement par carte ici.
        </flux:callout>

        <div class="grid gap-4 md:grid-cols-3">
            <article class="aydra-users-panel p-5">
                <flux:text>Échéances en attente ou à rapprocher</flux:text>
                <flux:heading size="lg">{{ $pendingDueCount }}</flux:heading>
            </article>
            <article class="aydra-users-panel p-5">
                <flux:text>Paiements intégraux confirmés</flux:text>
                <flux:heading size="lg">{{ $verifiedPaymentCount }}</flux:heading>
            </article>
            <article class="aydra-users-panel p-5">
                <flux:text>Documents</flux:text>
                <flux:button :href="route('owner.subscription-requests.index')" size="sm">Mes demandes</flux:button>
                <flux:button href="#owner-credits-heading" size="sm">Tous mes avoirs</flux:button>
            </article>
        </div>

        <section class="aydra-users-panel flex flex-col gap-4 p-5 sm:p-6" aria-labelledby="owner-dues-heading">
            <flux:heading id="owner-dues-heading" size="lg">1. Mes échéances</flux:heading>
            <flux:text>Les montants indiqués sont ceux de la période contractuelle, pas une autorisation de paiement partiel.</flux:text>
            <div class="overflow-x-auto">
                <flux:table class="whitespace-normal">
                    <flux:table.columns>
                        <flux:table.column>Période</flux:table.column><flux:table.column>Échéance</flux:table.column><flux:table.column>Montant intégral</flux:table.column>
                        <flux:table.column>Situation</flux:table.column><flux:table.column>Facture</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($dues as $due)
                            <flux:table.row>
                                <flux:table.cell>{{ $due->period_starts_at->format('d/m/Y') }} — {{ $due->period_ends_at->format('d/m/Y') }}<div class="text-xs">{{ $due->installment_number }}</div></flux:table.cell>
                                <flux:table.cell>{{ $due->due_at?->format('d/m/Y') ?? 'Non précisée' }}</flux:table.cell>
                                <flux:table.cell>{{ $due->installment_amount }} DZD</flux:table.cell>
                                <flux:table.cell>
                                    @if ($due->installment_status === \App\Enums\Central\Subscriptions\InstallmentStatusEnum::PARTIALLY_PAID)
                                        Historique à rapprocher par l'administration
                                    @else
                                        {{ $due->installment_status->label() }}
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @forelse ($due->invoices as $invoice)
                                        <a class="block underline" href="{{ route('owner.invoices.show', $invoice) }}">
                                            {{ $invoice->number ?? 'Préparation de facture' }} — {{ $invoice->status->label() }}
                                        </a>
                                    @empty
                                        <span>Aucune facture émise : consulter mon abonnement</span>
                                    @endforelse
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="5">Aucune échéance payante. Une offre gratuite n'impose aucun règlement.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
            {{ $dues->links() }}
        </section>

        <section class="aydra-users-panel flex flex-col gap-4 p-5 sm:p-6" aria-labelledby="owner-invoices-heading">
            <flux:heading id="owner-invoices-heading" size="lg">2. Factures et solde</flux:heading>
            <flux:text>Le solde net détaillé (avoirs, paiements confirmés, reste à payer) figure sur chaque facture. Une facture non émise n'est pas un justificatif PDF définitif.</flux:text>
            <div class="overflow-x-auto">
                <flux:table class="whitespace-normal">
                    <flux:table.columns><flux:table.column>Facture</flux:table.column><flux:table.column>Montant</flux:table.column><flux:table.column>État</flux:table.column><flux:table.column>Paiement</flux:table.column><flux:table.column>Actions</flux:table.column></flux:table.columns>
                    <flux:table.rows>
                        @forelse ($invoices as $invoice)
                            @php($state = $invoiceStates[$invoice->id])
                            <flux:table.row>
                                <flux:table.cell>{{ $invoice->number ?? 'En préparation' }}</flux:table.cell>
                                <flux:table.cell>{{ $invoice->total_amount }} {{ $invoice->currency }}</flux:table.cell>
                                <flux:table.cell>{{ $invoice->status->label() }}</flux:table.cell>
                                <flux:table.cell>{{ $state->label() }}</flux:table.cell>
                                <flux:table.cell><flux:dropdown position="bottom" align="end"><flux:button type="button" variant="ghost" size="sm" icon="bars-3" aria-label="Actions" title="Actions" /><flux:menu>
                                    <flux:menu.item href="{{ route('owner.invoices.show', $invoice) }}">Solde et détail</flux:menu.item>
                                    @if ($invoice->status === \App\Enums\Central\Billing\DocumentStatusEnum::ISSUED)
                                        @can('download', $invoice)
                                            <flux:menu.item href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('owner.invoices.download', now()->addMinutes(10), ['invoice' => $invoice]) }}">PDF privé</flux:menu.item>
                                        @endcan
                                        @if ($state === \App\Enums\Central\Billing\PaymentStateEnum::DUE)
                                            @can('declarePayment', $invoice)
                                                <flux:menu.item href="{{ route('owner.payments.create', $invoice) }}">Déposer un justificatif</flux:menu.item>
                                            @endcan
                                        @endif
                                    @endif
                                </flux:menu></flux:dropdown></flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="5">Aucune facture pour votre compte.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
            {{ $invoices->links() }}
        </section>

        <section class="aydra-users-panel flex flex-col gap-4 p-5 sm:p-6" aria-labelledby="owner-payments-heading">
            <flux:heading id="owner-payments-heading" size="lg">3. Paiements et reçus</flux:heading>
            <flux:text>Un reçu est disponible seulement après validation intégrale. Toute erreur est signalée pour examen, sans réécriture de l'encaissement.</flux:text>
            <div class="overflow-x-auto">
                <flux:table class="whitespace-normal">
                    <flux:table.columns><flux:table.column>Facture</flux:table.column><flux:table.column>Montant</flux:table.column><flux:table.column>Méthode</flux:table.column><flux:table.column>État</flux:table.column><flux:table.column>Actions</flux:table.column></flux:table.columns>
                    <flux:table.rows>
                        @forelse ($payments as $payment)
                            <flux:table.row>
                                <flux:table.cell>{{ $payment->invoice?->number ?? 'Document archivé' }}</flux:table.cell>
                                <flux:table.cell>{{ $payment->getAttribute('amount') }} {{ $payment->getAttribute('currency') }}</flux:table.cell>
                                <flux:table.cell>{{ $payment->getAttribute('transfer_method')->label() }}</flux:table.cell>
                                <flux:table.cell>{{ $payment->statusLabel() }}</flux:table.cell>
                                <flux:table.cell><flux:dropdown position="bottom" align="end"><flux:button type="button" variant="ghost" size="sm" icon="bars-3" aria-label="Actions" title="Actions" /><flux:menu>
                                    <flux:menu.item href="{{ route('owner.invoices.show', $payment->invoice).'#payment-'.$payment->uuid }}">Détails</flux:menu.item>
                                    @can('receipt', $payment)
                                        <flux:menu.item href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('owner.payments.receipt', now()->addMinutes(10), ['payment' => $payment]) }}">Reçu privé</flux:menu.item>
                                    @endcan
                                    @if ($payment->getAttribute('proof_media_id') !== null)
                                        <flux:menu.item href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('owner.payments.proof', now()->addMinutes(10), ['payment' => $payment]) }}">Justificatif</flux:menu.item>
                                    @endif
                                    <flux:menu.item href="{{ route('owner.invoices.show', $payment->invoice).'#payment-'.$payment->uuid }}">Signaler une erreur</flux:menu.item>
                                </flux:menu></flux:dropdown></flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="5">Aucun paiement déclaré.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
            {{ $payments->links() }}
        </section>

        <section class="aydra-users-panel flex flex-col gap-4 p-5 sm:p-6" aria-labelledby="owner-credits-heading">
            <flux:heading id="owner-credits-heading" size="lg">4. Avoirs et corrections documentaires</flux:heading>
            <flux:text>Une correction de facture est un document distinct, émis par l'administration : la facture d'origine reste consultable.</flux:text>
            <div class="overflow-x-auto">
                <flux:table class="whitespace-normal">
                    <flux:table.columns><flux:table.column>Avoir</flux:table.column><flux:table.column>Facture d'origine</flux:table.column><flux:table.column>Montant corrigé</flux:table.column><flux:table.column>Actions</flux:table.column></flux:table.columns>
                    <flux:table.rows>
                        @forelse ($credits as $credit)
                            <flux:table.row>
                                <flux:table.cell>{{ $credit->number }}</flux:table.cell>
                                <flux:table.cell>{{ $credit->originalInvoice?->number ?? 'Référence historique' }}</flux:table.cell>
                                <flux:table.cell>{{ $credit->total_amount }} {{ $credit->currency }}</flux:table.cell>
                                <flux:table.cell><flux:dropdown position="bottom" align="end"><flux:button type="button" variant="ghost" size="sm" icon="bars-3" aria-label="Actions" title="Actions" /><flux:menu>
                                    <flux:menu.item href="{{ route('owner.credit-notes.show', $credit) }}">Détails</flux:menu.item>
                                    @can('download', $credit)
                                        <flux:menu.item href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('owner.credit-notes.download', now()->addMinutes(10), ['credit' => $credit]) }}">PDF privé</flux:menu.item>
                                    @endcan
                                </flux:menu></flux:dropdown></flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="4">Aucun avoir émis.</flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
            {{ $credits->links() }}
        </section>

        <section class="aydra-users-panel flex flex-col gap-4 p-5 sm:p-6" aria-labelledby="owner-corrections-heading">
            <flux:heading id="owner-corrections-heading" size="lg">5. Mes signalements récents</flux:heading>
            <flux:text>Ces signalements et les décisions administratives sont conservés dans le journal du paiement. Leur dépôt n'est pas une validation.</flux:text>
            @forelse ($reports as $report)
                @php($decision = $reportDecisions->get($report->uuid))
                <article class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:text>{{ $report->created_at->format('d/m/Y H:i') }} — paiement PAY-{{ $report->properties['payment_uuid'] ?? 'Inconnu' }}</flux:text>
                    <flux:text>{{ $report->properties['reason'] ?? '' }}</flux:text>
                    <flux:text>
                        @if ($decision === null)
                            En attente
                        @elseif (($decision->properties['decision'] ?? null) === \App\Services\Central\Billing\OwnerPaymentCorrectionService::DECISION_CREDIT_NOTE)
                            Corrigé par avoir émis — {{ $decision->properties['credit_uuid'] ?? 'Avoir émis' }}
                        @else
                            Examiné — aucune correction nécessaire
                        @endif
                    </flux:text>
                    @if ($decision !== null)
                        <flux:text>{{ $decision->properties['note'] ?? '' }}</flux:text>
                    @endif
                </article>
            @empty
                <flux:text>Aucune demande de correction signalée.</flux:text>
            @endforelse
        </section>
    </section>
</x-layouts::app>
