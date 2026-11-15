@php($oldText = static fn (string $key, mixed $default = null): string => is_scalar($value = old($key, $default)) ? (string) $value : '')
<x-layouts::app title="Modifier les paramètres de facturation">
    <section class="mx-auto flex max-w-2xl flex-col gap-5">
        <flux:heading size="xl">Modifier les paramètres de facturation</flux:heading>
        <flux:text>Les nouveaux paramètres s’appliquent aux nouvelles préparations. Les documents et règles historiques restent conservés.</flux:text>
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <form method="POST" action="{{ route('central.billing-parameters.update', $rule) }}" class="flex flex-col gap-4">
            @csrf @method('PUT')
            <fieldset @disabled(! $editable) class="flex flex-col gap-4">
            @if ($rule)
                <input type="hidden" name="expected_state" value="{{ $rule->managementState() }}">
            @else
                <input type="hidden" name="operation_uuid" value="{{ $oldText('operation_uuid', $operationUuid) }}">
                <flux:select name="document_type" label="Document à configurer" required>
                    @foreach (\App\Enums\Central\Billing\DocumentTypeEnum::cases() as $type)<option value="{{ $type->value }}" @selected($oldText('document_type', 1) === (string) $type->value)>{{ $type->label() }}</option>@endforeach
                </flux:select>
            @endif
            <flux:input name="payment_due_days" type="number" min="0" label="Délai de paiement en jours (0 pour un avoir)" :value="$oldText('payment_due_days', $rule?->parameters['payment_due_days'])" required />
            <flux:textarea name="validation_reference" label="Référence de validation des paramètres" required maxlength="2000">{{ $oldText('validation_reference', $rule?->validation_reference) }}</flux:textarea>
            @if ($editable)<flux:button type="submit">Valider et enregistrer les paramètres</flux:button>@endif
            </fieldset>
        </form>
    </section>
</x-layouts::app>
