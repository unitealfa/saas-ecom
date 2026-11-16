@php($oldText = static fn (string $key, mixed $default = null): string => is_scalar($value = old($key, $default)) ? (string) $value : '')
        <form method="POST" enctype="multipart/form-data" class="flex flex-col gap-4" action="{{ route($cash ? 'central.payments.cash.store' : 'owner.payments.store', $invoice) }}">
            @csrf
            <input type="hidden" name="operation_id" value="{{ $oldText('operation_id', (string) \Illuminate\Support\Str::uuid()) }}">
            <input type="hidden" name="currency" value="{{ $invoice->currency }}">
            @if ($invoice->subscription->request)<input type="hidden" name="expected_request_state" value="{{ $invoice->subscription->request->managementState() }}">@endif
            <flux:input name="amount" label="Montant réellement reçu ou déclaré" inputmode="decimal" :value="$oldText('amount', $balance['net_due'])" required />
            <flux:input name="occurred_at" type="datetime-local" label="Date et heure réelles du paiement" :value="$oldText('occurred_at')" required />
            @if ($cash)
                <flux:checkbox name="received" value="1" label="Je confirme avoir physiquement reçu ces espèces." required />
                <flux:text>Un montant différent de la facture sera conservé comme anomalie, sans activer l’abonnement. Aucun justificatif bancaire n’est demandé.</flux:text>
            @else
                <flux:select name="method" label="Moyen du versement externe" required>
                    @foreach ([\App\Enums\Central\Billing\TransferMethodEnum::BANK_TRANSFER, \App\Enums\Central\Billing\TransferMethodEnum::CCP_TRANSFER, \App\Enums\Central\Billing\TransferMethodEnum::BARIDIMOB] as $method)
                        <option value="{{ $method->value }}" @selected($oldText('method') === (string) $method->value)>{{ $method->label() }}</option>
                    @endforeach
                </flux:select>
                <flux:input name="reference" label="Référence du justificatif, si disponible" :value="$oldText('reference')" />
                <flux:input type="file" name="proof" label="Photo JPEG/PNG ou PDF — 8 Mo maximum" accept="application/pdf,image/jpeg,image/png" required />
                <flux:text>Utilisez un versement déjà effectué hors Aydra. L’administration vérifiera la réception réelle des fonds. Aucun paiement par carte n’est proposé ici.</flux:text>
            @endif
            <flux:button type="submit" variant="primary">{{ $cash ? 'Enregistrer l’encaissement reçu' : 'Déposer le justificatif' }}</flux:button>
        </form>
