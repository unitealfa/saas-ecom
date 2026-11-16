@php
    $oldText = static fn (string $key, mixed $default = null): string => is_scalar($value = old($key, $default)) ? (string) $value : '';
    $previous = $oldText('operation_id');
    $operation = \Illuminate\Support\Str::isUuid($previous, version: 4) ? $previous : (string) \Illuminate\Support\Str::uuid();
@endphp
<x-layouts::app :title="$credit ? 'Modifier le brouillon d’avoir' : 'Préparer un avoir'">
    <section class="mx-auto flex w-full max-w-5xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $credit ? 'Modifier le brouillon d’avoir' : 'Préparer un avoir' }}</flux:heading>
        <flux:text>Facture {{ $invoice->number }} — {{ $invoice->total_amount }} {{ $invoice->currency }}.</flux:text>
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <form method="POST" action="{{ $credit ? route('central.credit-notes.update', $credit) : route('central.credit-notes.store', $invoice) }}" class="flex flex-col gap-5">
            @csrf
            @if ($credit) @method('PUT') @else<input type="hidden" name="operation_id" value="{{ $operation }}">@endif
            <input type="hidden" name="expected_state" value="{{ ($credit ?? $invoice)->managementState() }}">
            @php($applicableRule = $credit?->billingRule ?? ($rules->count() === 1 ? $rules->sole() : null))
            @if ($applicableRule)
                <input type="hidden" name="billing_rule_uuid" value="{{ $applicableRule->uuid }}">
            @else
                <flux:callout variant="warning">La configuration interne des avoirs est absente ou ambiguë. Aucun avoir ne peut être préparé pour le moment.</flux:callout>
            @endif

            @foreach ($available as $id => $item)
                @php($existing = $credit?->lines->firstWhere('original_invoice_line_id', $id))
                <fieldset class="flex flex-col gap-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <legend class="font-medium">{{ $item['line']->description }}</legend>
                    <input type="hidden" name="lines[{{ $id }}][original_line_uuid]" value="{{ $item['line']->getAttribute('uuid') }}">
                    <flux:input name="lines[{{ $id }}][total_amount]" label="Montant à retirer de la facture (DA)" inputmode="decimal" :value="$oldText('lines.'.$id.'.total_amount', $existing?->total_amount)" />
                    <flux:input name="lines[{{ $id }}][reason]" label="Motif de la correction" :value="$oldText('lines.'.$id.'.reason', $existing?->getAttribute('reason'))" maxlength="2000" />
                </fieldset>
            @endforeach
            @can('saas.credit-notes.issue')
                <flux:button type="submit" variant="primary" :disabled="! $applicableRule">Valider et émettre l’avoir</flux:button>
            @else
                <flux:button type="submit" variant="primary" :disabled="! $applicableRule">Enregistrer le brouillon</flux:button>
            @endcan
        </form>
    </section>
</x-layouts::app>
