@php
    $oldText = static fn (string $key, mixed $default = null): string => is_scalar($value = old($key, $default)) ? (string) $value : '';
    $previous = $oldText('operation_id');
    $operation = \Illuminate\Support\Str::isUuid($previous, version: 4) ? $previous : (string) \Illuminate\Support\Str::uuid();
@endphp
<x-layouts::app :title="$credit ? 'Modifier le brouillon d’avoir' : 'Préparer un avoir'">
    <section class="mx-auto flex w-full max-w-5xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $credit ? 'Modifier le brouillon d’avoir' : 'Préparer un avoir' }}</flux:heading>
        <flux:text>Facture {{ $invoice->number }} — {{ $invoice->total_amount }} {{ $invoice->currency }}. L’original reste conservé.</flux:text>
        <flux:text>Renseignez la quantité et le TTC uniquement pour les lignes à corriger. Les taxes sont calculées depuis la ventilation originale. Chaque plafond est contrôlé séparément.</flux:text>
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <form method="POST" action="{{ $credit ? route('central.credit-notes.update', $credit) : route('central.credit-notes.store', $invoice) }}" class="flex flex-col gap-5">
            @csrf
            @if ($credit) @method('PUT') @else<input type="hidden" name="operation_id" value="{{ $operation }}">@endif
            <input type="hidden" name="expected_state" value="{{ ($credit ?? $invoice)->managementState() }}">
            <flux:select name="billing_rule_uuid" label="Règle d’avoir validée et active" required>
                <option value="">Choisir une règle</option>
                @foreach ($rules as $rule)<option value="{{ $rule->uuid }}" @selected($oldText('billing_rule_uuid', $credit?->billingRule->uuid) === $rule->uuid)>{{ $rule->code }} — version {{ $rule->version }}</option>@endforeach
            </flux:select>
            <flux:textarea name="reason" label="Motif de l’avoir" required maxlength="2000">{{ $oldText('reason', $credit?->getAttribute('reason')) }}</flux:textarea>
            @foreach ($available as $id => $item)
                @php($existing = $credit?->lines->firstWhere('original_invoice_line_id', $id))
                <fieldset class="flex flex-col gap-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <legend class="font-medium">{{ $item['line']->description }}</legend>
                    <flux:text>Original : {{ $item['line']->quantity }} unité(s), HT {{ $item['line']->net_amount }}, taxes {{ $item['line']->tax_amount }}, TTC {{ $item['line']->total_amount }} {{ $invoice->currency }}.</flux:text>
                    <flux:text>Déjà corrigé : {{ $item['issued']['quantity'] }} unité(s), HT {{ $item['issued']['net_amount'] }}, taxes {{ $item['issued']['tax_amount'] }}, TTC {{ $item['issued']['total_amount'] }}.</flux:text>
                    <flux:text>Autres réservations : {{ $item['reserved']['quantity'] }} unité(s), HT {{ $item['reserved']['net_amount'] }}, taxes {{ $item['reserved']['tax_amount'] }}, TTC {{ $item['reserved']['total_amount'] }}.</flux:text>
                    <flux:text>Disponible : {{ $item['remaining']['quantity'] }} unité(s), HT {{ $item['remaining']['net_amount'] }}, taxes {{ $item['remaining']['tax_amount'] }}, TTC {{ $item['remaining']['total_amount'] }}.</flux:text>
                    <input type="hidden" name="lines[{{ $id }}][original_line_uuid]" value="{{ $item['line']->getAttribute('uuid') }}">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <flux:input name="lines[{{ $id }}][quantity]" label="Quantité à corriger" inputmode="decimal" :value="$oldText('lines.'.$id.'.quantity', $existing?->quantity)" placeholder="Ex. : 0.10" />
                        <flux:input name="lines[{{ $id }}][total_amount]" label="Correction TTC (DZD)" inputmode="decimal" :value="$oldText('lines.'.$id.'.total_amount', $existing?->total_amount)" placeholder="Ex. : 500.00" />
                    </div>
                    <flux:input name="lines[{{ $id }}][reason]" label="Motif de cette ligne" :value="$oldText('lines.'.$id.'.reason', $existing?->getAttribute('reason'))" maxlength="2000" />
                </fieldset>
            @endforeach
            <flux:button type="submit" variant="primary">{{ $credit ? 'Enregistrer les corrections' : 'Créer le brouillon et réserver les montants' }}</flux:button>
        </form>
    </section>
</x-layouts::app>
