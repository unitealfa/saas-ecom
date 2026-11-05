@php
    $oldText = static fn (string $key, mixed $default = null): string => is_scalar($value = old($key, $default)) ? (string) $value : '';
    $previousOperation = $oldText('operation_uuid');
    $operationUuid = \Illuminate\Support\Str::isUuid($previousOperation, version: 4) ? $previousOperation : $operationUuid;
    $editing = $rule !== null && ! $newVersion;
    $title = $newVersion ? 'Nouvelle version de règle' : ($editing ? 'Modifier le brouillon' : 'Créer une règle');
    $action = $newVersion ? route('central.billing-rules.versions.store', $rule) : ($editing ? route('central.billing-rules.update', $rule) : route('central.billing-rules.store'));
@endphp
<x-layouts::app :title="$title">
    <section class="mx-auto flex w-full max-w-2xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $title }}</flux:heading>
        <flux:text>Le contenu est modifiable en brouillon. Après validation, tout changement exige une nouvelle version.</flux:text>
        <flux:text>Chaque période correspond à un paiement intégral. Le délai indique une date limite unique, sans échéancier. Une facture ne confirme aucun encaissement ni activation de droits.</flux:text>
        @if ($newVersion)<flux:text>La nouvelle version conserve le code {{ $rule->code }}. Son numéro est attribué automatiquement.</flux:text>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <form method="POST" action="{{ $action }}" class="flex flex-col gap-5" x-data="{ submitting: false }" x-on:submit="submitting = true">
            @csrf
            @if ($editing) @method('PUT') @endif
            @if ($rule !== null)<input type="hidden" name="expected_state" value="{{ $rule->managementState() }}">@endif
            @unless ($editing)<input type="hidden" name="operation_uuid" value="{{ $operationUuid }}">@endunless
            <flux:input name="code" label="Code permanent" :value="$rule?->code ?? $oldText('code')" :readonly="$rule !== null" required placeholder="Ex. : abonnement.standard" />
            <flux:select name="trigger_event" label="Événement déclencheur" required>
                @foreach (\App\Enums\Central\Billing\TriggerEventEnum::cases() as $event)
                    <option value="{{ $event->value }}" @selected(old('trigger_event', $rule?->trigger_event->value) === $event->value)>{{ $event->label() }}</option>
                @endforeach
            </flux:select>
            <flux:select name="parameters[document_type]" label="Document à produire" required>
                <option value="{{ \App\Enums\Central\Billing\DocumentTypeEnum::INVOICE->value }}">Facture</option>
            </flux:select>
            <flux:input type="number" name="parameters[payment_due_days]" label="Délai de paiement en jours" min="0" step="1" :value="$oldText('parameters.payment_due_days', $rule?->parameters['payment_due_days'])" required description="Nombre de jours après l’émission. Saisissez 0 pour un paiement exigible le jour même." />
            <div class="flex flex-wrap gap-3">
                <flux:button type="submit" variant="primary" x-bind:disabled="submitting" x-bind:aria-busy="submitting">Enregistrer le brouillon</flux:button>
                @can('viewAny', \App\Models\Central\Billing\BillingRule::class)
                    <flux:button :href="route('central.billing-rules.index')">Retour à la configuration</flux:button>
                @endcan
            </div>
            <flux:text x-cloak x-show="submitting" role="status">Enregistrement en cours…</flux:text>
        </form>
    </section>
</x-layouts::app>
