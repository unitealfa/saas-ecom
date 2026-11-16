@php($paymentText = static fn (string $key, mixed $default = null): string => is_scalar($value = old($key, $default)) ? (string) $value : '')
<flux:select name="transition_mode" label="Début de la nouvelle période">
    @foreach (\App\Enums\Central\Subscriptions\TransitionModeEnum::cases() as $mode)
        <option value="{{ $mode->value }}" @selected($paymentText('transition_mode', 2) === (string) $mode->value)>{{ $mode->label() }}</option>
    @endforeach
</flux:select>
<flux:text>Tarif intégral, sans prorata ni remboursement automatique. Les droits actuels restent conservés avant confirmation.</flux:text>
<div x-data="{ choice: @js($paymentText('payment_choice', '1')) }" class="flex flex-col gap-3">
    <flux:select name="payment_choice" label="Moyen de paiement" x-model="choice" required>
        <option value="1">Espèces au bureau Aydra</option>
        <option value="2">Versement externe avec justificatif</option>
    </flux:select>
    <div x-show="choice === '2'" x-cloak class="flex flex-col gap-3">
        <fieldset x-bind:disabled="choice !== '2'" class="flex flex-col gap-3">
            <flux:text>{{ config('central.billing.payment_instructions') ?: 'Demandez les coordonnées officielles de versement à l’administration.' }}</flux:text>
            <flux:select name="payment[method]" label="Réseau du versement" required>
                @foreach ([\App\Enums\Central\Billing\TransferMethodEnum::BANK_TRANSFER, \App\Enums\Central\Billing\TransferMethodEnum::CCP_TRANSFER, \App\Enums\Central\Billing\TransferMethodEnum::BARIDIMOB] as $method)
                    <option value="{{ $method->value }}" @selected($paymentText('payment.method') === (string) $method->value)>{{ $method->label() }}</option>
                @endforeach
            </flux:select>
            <flux:input name="payment[amount]" label="Montant réellement versé (DZD)" :value="$paymentText('payment.amount')" required />
            <flux:input name="payment[reference]" label="Référence du versement" :value="$paymentText('payment.reference')" maxlength="255" />
            <flux:input type="file" name="proof" label="Justificatif PDF/JPG/PNG (8 Mo maximum)" accept="application/pdf,image/jpeg,image/png" required />
        </fieldset>
    </div>
</div>
