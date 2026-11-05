<x-layouts::app title="Échéances de paiement">
    <section class="mx-auto flex w-full max-w-5xl flex-col gap-6">
        <flux:heading size="xl" level="1">Échéances de l’abonnement</flux:heading>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3">Échéance</th><th class="p-3">Montant dû</th><th class="p-3">Date limite</th><th class="p-3">État</th><th class="p-3">Facture</th></tr></thead>
                <tbody>
                    @forelse ($installments as $installment)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td class="p-3">{{ $installment->installment_number }}</td><td class="p-3">{{ $installment->installment_amount }} DZD</td>
                            <td class="p-3">{{ $installment->due_at->format('d/m/Y') }}</td><td class="p-3">{{ $installment->installment_status->label() }}</td>
                            <td class="p-3">
                                @if ($installment->invoices->isEmpty() && $installment->matchesFullPeriod($subscription)
                                    && $installment->installment_status === \App\Enums\Central\Subscriptions\InstallmentStatusEnum::PENDING)
                                    @can('create', \App\Models\Central\Billing\SaasInvoice::class)
                                        <form method="POST" action="{{ route('central.invoices.installment.store', ['subscription' => $subscription, 'installment' => $installment]) }}">
                                            @csrf
                                            <input type="hidden" name="expected_state" value="{{ $installment->managementState() }}">
                                            <flux:select name="billing_rule_uuid" label="Règle applicable" required>
                                                @foreach ($rules as $rule)<option value="{{ $rule->uuid }}">{{ $rule->code }} — V{{ $rule->version }}</option>@endforeach
                                            </flux:select>
                                            <flux:button type="submit" size="sm">Préparer la facture de cette échéance</flux:button>
                                        </form>
                                    @endcan
                                @endif
                                @foreach ($installment->invoices as $invoice)
                                    @can('view', $invoice)<flux:button size="sm" :href="route('central.invoices.show', $invoice)">{{ $invoice->number ?? 'Brouillon' }}</flux:button>@endcan
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td class="p-3" colspan="5">Aucune échéance.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $installments->links() }}
    </section>
</x-layouts::app>
