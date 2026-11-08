<x-layouts::app title="Signalements de paiements SaaS">
    <section class="mx-auto flex w-full max-w-6xl flex-col gap-5">
        <flux:heading size="xl" level="1">Signalements de paiements SaaS</flux:heading>
        @if (session('status'))
            <flux:callout>{{ session('status') }}</flux:callout>
        @endif
        @foreach ($errors->all() as $error)
            <flux:text role="alert">{{ $error }}</flux:text>
        @endforeach
        <flux:callout>
            Liste des demandes soumises par les commerçants. Un signalement n'est ni un remboursement,
            ni un avoir, ni une validation. Examinez le paiement et son historique avant toute décision.
            Une décision de correction exige un avoir déjà émis pour cette facture.
        </flux:callout>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3">Date</th><th class="p-3">Paiement</th><th class="p-3">Motif</th><th class="p-3">Décision</th><th class="p-3">Action</th></tr></thead>
                <tbody>
                    @forelse ($reports as $report)
                        @php
                            $decision = $reportDecisions->get($report->uuid);
                            $credits = $report->subject instanceof \App\Models\Central\Billing\SaasPayment
                                ? $creditsByInvoiceId->get($report->subject->getAttribute('document_id'), collect())
                                : collect();
                            if ($report->subject instanceof \App\Models\Central\Billing\SaasPayment) {
                                $credits = $credits->filter(
                                    fn (\App\Models\Central\Billing\SaasCreditNote $credit): bool => (int) $credit->getAttribute('user_id')
                                        === (int) $report->subject->getAttribute('user_id'),
                                );
                            }
                        @endphp
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td class="p-3">{{ $report->created_at->format('d/m/Y H:i') }}</td>
                            <td class="p-3">PAY-{{ $report->properties['payment_uuid'] ?? 'Inconnu' }}</td>
                            <td class="p-3">{{ $report->properties['reason'] ?? 'Motif manquant' }}</td>
                            <td class="p-3">
                                @if ($decision !== null)
                                    @if (($decision->properties['decision'] ?? null) === \App\Services\Central\Billing\OwnerPaymentCorrectionService::DECISION_CREDIT_NOTE)
                                        Corrigé par avoir émis — {{ $decision->properties['credit_uuid'] ?? 'Avoir émis' }}
                                    @else
                                        Examiné — aucune correction nécessaire
                                    @endif
                                    <div>{{ $decision->properties['note'] ?? '' }}</div>
                                @else
                                    En attente
                                @endif
                            </td>
                            <td class="p-3">
                                @if ($report->subject instanceof \App\Models\Central\Billing\SaasPayment)
                                    <a class="underline" href="{{ route('central.payments.show', $report->subject) }}">Examiner le paiement</a>
                                    @if ($decision === null && $canReview)
                                        <div class="mt-3 flex flex-col gap-3">
                                            <form method="POST" action="{{ route('central.payments.corrections.review', $report) }}">
                                                @csrf
                                                <input type="hidden" name="decision" value="{{ \App\Services\Central\Billing\OwnerPaymentCorrectionService::DECISION_EXAMINED }}">
                                                <flux:textarea name="note" label="Note de décision" rows="3" required minlength="10" maxlength="2000" />
                                                <flux:button type="submit" size="sm">Examiné — aucune correction nécessaire</flux:button>
                                            </form>
                                            @if ($credits->isNotEmpty())
                                                <form method="POST" action="{{ route('central.payments.corrections.review', $report) }}" class="flex flex-col gap-2">
                                                    @csrf
                                                    <input type="hidden" name="decision" value="{{ \App\Services\Central\Billing\OwnerPaymentCorrectionService::DECISION_CREDIT_NOTE }}">
                                                    <flux:textarea name="note" label="Note de décision" rows="3" required minlength="10" maxlength="2000" />
                                                    <flux:select name="credit_uuid" label="Avoir émis" required>
                                                        <option value="">Sélectionner un avoir</option>
                                                        @foreach ($credits as $credit)
                                                            <option value="{{ $credit->uuid }}">{{ $credit->number }} — {{ $credit->total_amount }} {{ $credit->currency }}</option>
                                                        @endforeach
                                                    </flux:select>
                                                    <flux:button type="submit" size="sm">Corrigé par avoir émis</flux:button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif
                                @else
                                    <span>Référence historique à rapprocher</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="p-3" colspan="5">Aucun signalement reçu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $reports->links() }}
        <flux:button :href="route('central.payments.index')">Tous les paiements</flux:button>
    </section>
</x-layouts::app>
