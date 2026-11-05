@php
    $oldText = static fn (string $key, mixed $default = null): string => is_scalar($value = old($key, $default)) ? (string) $value : '';
@endphp
<x-layouts::app :title="'Règle '.$rule->code">
    <section class="mx-auto flex w-full max-w-3xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $rule->code }} — Version {{ $rule->version }}</flux:heading>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <flux:text>Dates et heures exprimées dans le fuseau {{ config('app.timezone') }}.</flux:text>
        <dl class="grid gap-4 sm:grid-cols-2">
            <div><dt>État</dt><dd>{{ $rule->policy_status->label() }}</dd></div>
            <div><dt>Événement</dt><dd>{{ $rule->trigger_event->label() }}</dd></div>
            <div><dt>Document</dt><dd>{{ \App\Enums\Central\Billing\DocumentTypeEnum::from($rule->parameters['document_type'])->label() }}</dd></div>
            <div><dt>Délai de paiement</dt><dd>{{ $rule->parameters['payment_due_days'] }} jours</dd></div>
            <div><dt>Date d’effet</dt><dd>{{ $rule->effective_at?->format('d/m/Y H:i') ?? 'Non définie' }}</dd></div>
            <div><dt>Fin d’effet</dt><dd>{{ $rule->ends_at?->format('d/m/Y H:i') ?? 'Sans fin prévue' }}</dd></div>
            <div><dt>Créée par</dt><dd>{{ $rule->creator?->name ?? 'Système' }}</dd></div>
            <div><dt>Validée par</dt><dd>{{ $rule->validator?->name ?? 'Non validée' }}</dd></div>
            <div><dt>Validation</dt><dd>{{ $rule->validated_at?->format('d/m/Y H:i') ?? 'Non validée' }}</dd></div>
            <div><dt>Référence de validation</dt><dd class="whitespace-pre-wrap">{{ $rule->validation_reference ?? 'Non renseignée' }}</dd></div>
        </dl>
        @if ($rule->policy_status === \App\Enums\Central\Billing\PolicyStatusEnum::RETIRED && $rule->ends_at?->isFuture())
            <flux:callout>Retrait programmé : fin historique au {{ $rule->ends_at->format('d/m/Y H:i') }}. Cette version n’est plus proposée pour une nouvelle facturation ; les documents déjà préparés sont conservés.</flux:callout>
        @endif
        <div class="flex flex-wrap gap-3">
            @can('update', $rule)<flux:button :href="route('central.billing-rules.edit', $rule)">Modifier le brouillon</flux:button>@endcan
            @can('createVersion', $rule)<flux:button :href="route('central.billing-rules.versions.create', $rule)">Nouvelle version</flux:button>@endcan
            <flux:button :href="route('central.billing-rules.index')">Retour à la configuration</flux:button>
        </div>
        @can('validateRule', $rule)
            <form method="POST" action="{{ route('central.billing-rules.validate', $rule) }}" class="flex flex-col gap-4"
                x-data="{ submitting: false }" x-on:submit="if (! window.confirm('Valider cette version et figer son contenu ?')) { $event.preventDefault(); return; } submitting = true">
                @csrf @method('PATCH')
                <input type="hidden" name="expected_state" value="{{ $rule->managementState() }}">
                <flux:heading>Valider cette version</flux:heading>
                <flux:text>La validation fige le contenu. L’activation avec une date d’effet restera une action distincte.</flux:text>
                <flux:textarea name="validation_reference" label="Référence de validation" required>{{ $oldText('validation_reference') }}</flux:textarea>
                <flux:button type="submit" variant="primary" x-bind:disabled="submitting" x-bind:aria-busy="submitting">Valider la version</flux:button>
                <flux:text x-cloak x-show="submitting" role="status">Validation en cours…</flux:text>
            </form>
        @endcan
        @can('activate', $rule)
            @if ($rule->policy_status === \App\Enums\Central\Billing\PolicyStatusEnum::ACTIVE)
                <form method="POST" action="{{ route('central.billing-rules.retire', $rule) }}" class="flex flex-col gap-4"
                    x-data="{ submitting: false }" x-on:submit="if (! window.confirm('Confirmer le retrait à la date indiquée ?')) { $event.preventDefault(); return; } submitting = true">
                    @csrf @method('PATCH')
                    <input type="hidden" name="expected_state" value="{{ $rule->managementState() }}">
                    <flux:heading>Retirer cette version</flux:heading>
                    <flux:text>Le retrait ferme la période. Il ne modifie pas les documents ou paramètres historiques. Une version retirée ne peut pas être réactivée.</flux:text>
                    <flux:input type="datetime-local" name="ends_at" label="Date de fermeture future" :value="$oldText('ends_at', $rule->ends_at?->format('Y-m-d\TH:i'))" required />
                    <flux:textarea name="reason" label="Motif obligatoire" required>{{ $oldText('reason') }}</flux:textarea>
                    <flux:button type="submit" variant="danger" x-bind:disabled="submitting" x-bind:aria-busy="submitting">Enregistrer le retrait</flux:button>
                    <flux:text x-cloak x-show="submitting" role="status">Retrait en cours…</flux:text>
                </form>
            @else
                <form method="POST" action="{{ route('central.billing-rules.activate', $rule) }}" class="flex flex-col gap-4"
                    x-data="{ submitting: false }" x-on:submit="if (! window.confirm('Activer cette version sur la période indiquée ?')) { $event.preventDefault(); return; } submitting = true">
                    @csrf @method('PATCH')
                    <input type="hidden" name="expected_state" value="{{ $rule->managementState() }}">
                    <flux:heading>Activer cette version validée</flux:heading>
                    <flux:text>La validation est conservée. Les périodes de deux versions du même code ne peuvent pas se chevaucher.</flux:text>
                    <flux:input type="datetime-local" name="effective_at" label="Date d’effet" :value="$oldText('effective_at', $rule->effective_at?->format('Y-m-d\TH:i'))" required />
                    <flux:input type="datetime-local" name="ends_at" label="Fin d’effet facultative" :value="$oldText('ends_at', $rule->ends_at?->format('Y-m-d\TH:i'))" />
                    <flux:button type="submit" variant="primary" x-bind:disabled="submitting" x-bind:aria-busy="submitting">Activer la version</flux:button>
                    <flux:text x-cloak x-show="submitting" role="status">Activation en cours…</flux:text>
                </form>
            @endif
        @endcan
        <flux:heading>Historique des versions</flux:heading>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr><th class="p-3">Version</th><th class="p-3">État</th><th class="p-3">Période</th><th class="p-3">Référence de validation</th></tr></thead>
                <tbody>
                    @foreach ($versions as $version)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td class="p-3"><a href="{{ route('central.billing-rules.show', $version) }}" class="underline">Version {{ $version->version }}</a></td>
                            <td class="p-3">{{ $version->policy_status->label() }}</td>
                            <td class="p-3">{{ $version->effective_at?->format('d/m/Y H:i') ?? 'Non définie' }} — {{ $version->ends_at?->format('d/m/Y H:i') ?? 'Sans fin prévue' }}</td>
                            <td class="p-3">{{ $version->validation_reference ?? 'Non validée' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $versions->links() }}
    </section>
</x-layouts::app>
