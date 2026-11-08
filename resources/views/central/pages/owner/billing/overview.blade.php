<x-layouts::app title="Ma facturation SaaS">
    <section class="mx-auto flex w-full max-w-7xl flex-col gap-7">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <flux:heading size="xl" level="1">Ma facturation SaaS</flux:heading>
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
            <article class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text>Échéances en attente ou à rapprocher</flux:text>
                <flux:heading size="lg">{{ $pendingDueCount }}</flux:heading>
            </article>
            <article class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text>Paiements intégraux confirmés</flux:text>
                <flux:heading size="lg">{{ $verifiedPaymentCount }}</flux:heading>
            </article>
            <article class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:text>Documents</flux:text>
                <flux:button :href="route('owner.invoices.index')" size="sm">Toutes mes factures</flux:button>
                <flux:button :href="route('owner.credit-notes.index')" size="sm">Tous mes avoirs</flux:button>
            </article>
        </div>

        <section class="flex flex-col gap-3" aria-labelledby="owner-dues-heading">
            <flux:heading id="owner-dues-heading" size="lg">1. Mes échéances</flux:heading>
            <flux:text>Les montants indiqués sont ceux de la période contractuelle, pas une autorisation de paiement partiel.</flux:text>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr>
                        <th class="p-3">Période</th><th class="p-3">Échéance</th><th class="p-3">Montant intégral</th>
                        <th class="p-3">Situation</th><th class="p-3">Facture</th>
                    </tr></thead>
                    <tbody>
                        @forelse ($dues as $due)
                            <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                <td class="p-3">{{ $due->period_starts_at->format('d/m/Y') }} — {{ $due->period_ends_at->format('d/m/Y') }}<div class="text-xs">{{ $due->installment_number }}</div></td>
                                <td class="p-3">{{ $due->due_at?->format('d/m/Y') ?? 'Non précisée' }}</td>
                                <td class="p-3">{{ $due->installment_amount }} DZD</td>
                                <td class="p-3">
                                    @if ($due->installment_status === \App\Enums\Central\Subscriptions\InstallmentStatusEnum::PARTIALLY_PAID)
                                        Historique à rapprocher par l'administration
                                    @else
                                        {{ $due->installment_status->label() }}
                                    @endif
                                </td>
                                <td class="p-3">
                                    @forelse ($due->invoices as $invoice)
                                        <a class="block underline" href="{{ route('owner.invoices.show', $invoice) }}">
                                            {{ $invoice->number ?? 'Préparation de facture' }} — {{ $invoice->status->label() }}
                                        </a>
                                    @empty
                                        <span>Aucune facture émise : consulter mon abonnement</span>
                                    @endforelse
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-3">Aucune échéance payante. Une offre gratuite n'impose aucun règlement.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $dues->links() }}
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="owner-invoices-heading">
            <flux:heading id="owner-invoices-heading" size="lg">2. Factures et solde</flux:heading>
            <flux:text>Le solde net détaillé (avoirs, paiements confirmés, reste à payer) figure sur chaque facture. Une facture non émise n'est pas un justificatif PDF définitif.</flux:text>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr><th class="p-3">Facture</th><th class="p-3">Montant</th><th class="p-3">État</th><th class="p-3">Paiement</th><th class="p-3">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            @php($state = $invoiceStates[$invoice->id])
                            <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                <td class="p-3">{{ $invoice->number ?? 'En préparation' }}</td>
                                <td class="p-3">{{ $invoice->total_amount }} {{ $invoice->currency }}</td>
                                <td class="p-3">{{ $invoice->status->label() }}</td>
                                <td class="p-3">{{ $state->label() }}</td>
                                <td class="p-3">
                                    <a class="mr-3 underline" href="{{ route('owner.invoices.show', $invoice) }}">Solde et détail</a>
                                    @if ($invoice->status === \App\Enums\Central\Billing\DocumentStatusEnum::ISSUED)
                                        @can('download', $invoice)
                                            <a class="mr-3 underline" href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('owner.invoices.download', now()->addMinutes(10), ['invoice' => $invoice]) }}">PDF privé</a>
                                        @endcan
                                        @if ($state === \App\Enums\Central\Billing\PaymentStateEnum::DUE)
                                            @can('declarePayment', $invoice)
                                                <a class="underline" href="{{ route('owner.payments.create', $invoice) }}">Déposer un justificatif</a>
                                            @endcan
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-3">Aucune facture pour votre compte.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $invoices->links() }}
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="owner-payments-heading">
            <flux:heading id="owner-payments-heading" size="lg">3. Paiements et reçus</flux:heading>
            <flux:text>Un reçu est disponible seulement après validation intégrale. Toute erreur est signalée pour examen, sans réécriture de l'encaissement.</flux:text>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr><th class="p-3">Facture</th><th class="p-3">Montant</th><th class="p-3">Méthode</th><th class="p-3">État</th><th class="p-3">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                <td class="p-3">{{ $payment->invoice?->number ?? 'Document archivé' }}</td>
                                <td class="p-3">{{ $payment->getAttribute('amount') }} {{ $payment->getAttribute('currency') }}</td>
                                <td class="p-3">{{ $payment->getAttribute('transfer_method')->label() }}</td>
                                <td class="p-3">{{ $payment->statusLabel() }}</td>
                                <td class="p-3">
                                    <a class="mr-3 underline" href="{{ route('owner.payments.show', $payment) }}">Détails</a>
                                    @can('receipt', $payment)
                                        <a class="mr-3 underline" href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('owner.payments.receipt', now()->addMinutes(10), ['payment' => $payment]) }}">Reçu privé</a>
                                    @endcan
                                    @if ($payment->getAttribute('proof_media_id') !== null)
                                        <a class="mr-3 underline" href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('owner.payments.proof', now()->addMinutes(10), ['payment' => $payment]) }}">Justificatif</a>
                                    @endif
                                    <a class="underline" href="{{ route('owner.billing.corrections.create', $payment) }}">Signaler une erreur</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-3">Aucun paiement déclaré.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $payments->links() }}
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="owner-credits-heading">
            <flux:heading id="owner-credits-heading" size="lg">4. Avoirs et corrections documentaires</flux:heading>
            <flux:text>Une correction de facture est un document distinct, émis par l'administration : la facture d'origine reste consultable.</flux:text>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead><tr><th class="p-3">Avoir</th><th class="p-3">Facture d'origine</th><th class="p-3">Montant corrigé</th><th class="p-3">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($credits as $credit)
                            <tr class="border-t border-zinc-200 dark:border-zinc-700">
                                <td class="p-3">{{ $credit->number }}</td>
                                <td class="p-3">{{ $credit->originalInvoice?->number ?? 'Référence historique' }}</td>
                                <td class="p-3">{{ $credit->total_amount }} {{ $credit->currency }}</td>
                                <td class="p-3">
                                    <a class="mr-3 underline" href="{{ route('owner.credit-notes.show', $credit) }}">Détails</a>
                                    @can('download', $credit)
                                        <a class="underline" href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('owner.credit-notes.download', now()->addMinutes(10), ['credit' => $credit]) }}">PDF privé</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-3">Aucun avoir émis.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $credits->links() }}
        </section>

        <section class="flex flex-col gap-3" aria-labelledby="owner-corrections-heading">
            <flux:heading id="owner-corrections-heading" size="lg">5. Mes signalements récents</flux:heading>
            <flux:text>Ces signalements sont conservés dans le journal du paiement et visibles par l'administration. Leur dépôt n'est pas une validation.</flux:text>
            @forelse ($reports as $report)
                <article class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:text>{{ $report->created_at->format('d/m/Y H:i') }} — paiement PAY-{{ $report->properties['payment_uuid'] ?? 'Inconnu' }}</flux:text>
                    <flux:text>{{ $report->properties['reason'] ?? '' }}</flux:text>
                </article>
            @empty
                <flux:text>Aucune demande de correction signalée.</flux:text>
            @endforelse
        </section>
    </section>
</x-layouts::app>
