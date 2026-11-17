<x-layouts::app title="Avoir SaaS">
    <section class="mx-auto flex w-full max-w-5xl flex-col gap-6">
        <flux:heading size="xl" level="1">Avoir — {{ $credit->number ?? 'Brouillon' }}</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <flux:text>État : {{ $credit->status->label() }}. Facture corrigée : {{ $credit->originalInvoice->number }}.</flux:text>
        <flux:text>Motif : {{ $credit->getAttribute('reason') }}</flux:text>
        <flux:text>Période : {{ $credit->period_starts_at->format('d/m/Y') }} — {{ $credit->period_ends_at->format('d/m/Y') }}.</flux:text>
        <flux:table>
            <flux:table.columns><flux:table.column>Prestation</flux:table.column><flux:table.column>Quantité</flux:table.column><flux:table.column>HT</flux:table.column><flux:table.column>Taxes</flux:table.column><flux:table.column>TTC</flux:table.column><flux:table.column>Motif</flux:table.column></flux:table.columns>
            <flux:table.rows>
                @foreach ($credit->lines as $line)
                    <flux:table.row><flux:table.cell>{{ $line->description }}</flux:table.cell><flux:table.cell>{{ $line->quantity }}</flux:table.cell><flux:table.cell>{{ $line->net_amount }}</flux:table.cell><flux:table.cell>{{ $line->tax_amount }}</flux:table.cell><flux:table.cell>{{ $line->total_amount }}</flux:table.cell><flux:table.cell>{{ $line->getAttribute('reason') }}</flux:table.cell></flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
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
            @if (in_array($credit->status, [\App\Enums\Central\Billing\DocumentStatusEnum::DRAFT, \App\Enums\Central\Billing\DocumentStatusEnum::PREPARING], true))
                @can('issue', $credit)<form method="POST" action="{{ route('central.credit-notes.issue', $credit) }}">@csrf<input type="hidden" name="expected_state" value="{{ $expectedState }}"><flux:button type="submit" variant="primary">Valider et émettre l’avoir</flux:button></form>@endcan
                @can('cancel', $credit)<form method="POST" action="{{ route('central.credit-notes.cancel', $credit) }}" class="flex flex-col gap-3">@csrf<input type="hidden" name="expected_state" value="{{ $expectedState }}"><flux:textarea name="reason" label="Motif de l’annulation" required maxlength="2000" /><flux:button type="submit">Annuler le brouillon</flux:button></form>@endcan
            @endif
            @if ($history !== null)<flux:heading>Historique</flux:heading>@foreach ($history as $event)<flux:text>{{ $event->created_at->format('d/m/Y H:i') }} — {{ $event->description }}</flux:text>@endforeach{{ $history->links() }}@endif
        @endunless
        @unless ($ownerMode)
            @include('central.pages.admin.billing.document-delivery', ['document' => $credit])
        @endunless
        @can($ownerMode ? 'viewOwnInvoice' : 'view', $credit->originalInvoice)
            <flux:button :href="route($ownerMode ? 'owner.invoices.show' : 'central.invoices.show', $credit->originalInvoice)">Facture originale</flux:button>
        @endcan
        <flux:button :href="route($ownerMode ? 'owner.credit-notes.index' : 'central.credit-notes.index')">Retour aux avoirs</flux:button>
    </section>
</x-layouts::app>
