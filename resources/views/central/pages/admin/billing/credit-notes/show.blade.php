<x-layouts::app title="Avoir SaaS">
    <section class="mx-auto flex w-full max-w-5xl flex-col gap-6">
        <flux:heading size="xl" level="1">Avoir — {{ $credit->number ?? 'Brouillon' }}</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <flux:text>État : {{ $credit->status->label() }}. Facture corrigée : {{ $credit->originalInvoice->number }}.</flux:text>
        <flux:text>Motif : {{ $credit->getAttribute('reason') }}</flux:text>
        <flux:text>Période : {{ $credit->period_starts_at->format('d/m/Y') }} — {{ $credit->period_ends_at->format('d/m/Y') }}.</flux:text>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th>Prestation</th><th>Quantité</th><th>HT</th><th>Taxes</th><th>TTC</th><th>Motif</th></tr></thead><tbody>
        @foreach ($credit->lines as $line)<tr><td class="p-3">{{ $line->description }}</td><td>{{ $line->quantity }}</td><td>{{ $line->net_amount }}</td><td>{{ $line->tax_amount }}</td><td>{{ $line->total_amount }}</td><td>{{ $line->getAttribute('reason') }}</td></tr>@endforeach
        </tbody></table></div>
        <flux:heading>Total corrigé : {{ $credit->total_amount }} {{ $credit->currency }}</flux:heading>
        <flux:text>Dû net : {{ $balance['net_due'] }} — paiements nets : {{ $balance['payments'] }} — remboursements nets : {{ $balance['refunds'] }} — reste à payer : {{ $balance['remaining'] }} — surplus : {{ $balance['surplus'] }} {{ $credit->currency }}.</flux:text>
        <flux:callout>Un brouillon réserve son montant, mais seul un avoir émis réduit le dû. Aucun remboursement ni changement d’abonnement n’est déclenché automatiquement.</flux:callout>
        @can('download', $credit)<flux:button :href="\Illuminate\Support\Facades\URL::temporarySignedRoute($ownerMode ? 'owner.credit-notes.download' : 'central.credit-notes.download', now()->addMinutes(10), ['credit' => $credit])">Télécharger le PDF privé de l’avoir</flux:button>@endcan
        @unless ($ownerMode)
            @php($expectedState = $credit->managementState())
            @foreach ($available as $item)
                <flux:text>{{ $item['line']->description }} — déjà corrigé : {{ $item['issued']['quantity'] }} unité(s), HT {{ $item['issued']['net_amount'] }}, taxes {{ $item['issued']['tax_amount'] }}, TTC {{ $item['issued']['total_amount'] }}.</flux:text>
                <flux:text>Réservé : {{ $item['reserved']['quantity'] }} unité(s), HT {{ $item['reserved']['net_amount'] }}, taxes {{ $item['reserved']['tax_amount'] }}, TTC {{ $item['reserved']['total_amount'] }}. Disponible : {{ $item['remaining']['quantity'] }} unité(s), HT {{ $item['remaining']['net_amount'] }}, taxes {{ $item['remaining']['tax_amount'] }}, TTC {{ $item['remaining']['total_amount'] }}.</flux:text>
            @endforeach
            @can('update', $credit)<flux:button :href="route('central.credit-notes.edit', $credit)">Modifier le brouillon</flux:button>@endcan
            @if ($credit->status === \App\Enums\Central\Billing\DocumentStatusEnum::DRAFT)
                @can('reserveNumber', $credit)<form method="POST" action="{{ route('central.credit-notes.prepare', $credit) }}">@csrf<input type="hidden" name="expected_state" value="{{ $expectedState }}"><flux:button type="submit">Valider et réserver le numéro</flux:button></form>@endcan
            @endif
            @if (in_array($credit->status, [\App\Enums\Central\Billing\DocumentStatusEnum::DRAFT, \App\Enums\Central\Billing\DocumentStatusEnum::PREPARING], true))
                @can('issue', $credit)<form method="POST" action="{{ route('central.credit-notes.issue', $credit) }}">@csrf<input type="hidden" name="expected_state" value="{{ $expectedState }}"><flux:button type="submit" variant="primary">Émettre l’avoir avec son PDF</flux:button></form>@endcan
                @can('cancel', $credit)<form method="POST" action="{{ route('central.credit-notes.cancel', $credit) }}" class="flex flex-col gap-3">@csrf<input type="hidden" name="expected_state" value="{{ $expectedState }}"><flux:textarea name="reason" label="Motif de l’annulation" required maxlength="2000" /><flux:button type="submit">Annuler le brouillon</flux:button></form>@endcan
            @endif
            @if ($history !== null)<flux:heading>Historique</flux:heading>@foreach ($history as $event)<flux:text>{{ $event->created_at->format('d/m/Y H:i') }} — {{ $event->description }}</flux:text>@endforeach{{ $history->links() }}@endif
        @endunless
        @unless ($ownerMode)
            @if ($credit->status === \App\Enums\Central\Billing\DocumentStatusEnum::ISSUED)
                @can('send', \App\Models\Central\Billing\SaasDocumentDelivery::class)
                    <form method="POST" action="{{ route('central.deliveries.send') }}">
                        @csrf
                        <input type="hidden" name="document_uuid" value="{{ $credit->uuid }}">
                        <flux:button type="submit">
                            Envoyer l'avoir par e-mail
                        </flux:button>
                    </form>
                @endcan

                @can('viewAny', \App\Models\Central\Billing\SaasDocumentDelivery::class)
                    <flux:button :href="route('central.deliveries.index', ['document_uuid' => $credit->uuid])">
                        Historique des envois
                    </flux:button>
                @endcan
            @endif
        @endunless
        @can($ownerMode ? 'viewOwnInvoice' : 'view', $credit->originalInvoice)
            <flux:button :href="route($ownerMode ? 'owner.invoices.show' : 'central.invoices.show', $credit->originalInvoice)">Facture originale</flux:button>
        @endcan
        <flux:button :href="route($ownerMode ? 'owner.credit-notes.index' : 'central.credit-notes.index')">Retour aux avoirs</flux:button>
    </section>
</x-layouts::app>
