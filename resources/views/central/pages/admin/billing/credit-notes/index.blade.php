<x-layouts::app :title="$ownerMode ? 'Mes avoirs' : 'Avoirs SaaS'">
    <section class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <flux:heading size="xl" level="1">{{ $ownerMode ? 'Mes avoirs' : 'Avoirs SaaS' }}</flux:heading>
        <flux:text>Un avoir corrige une facture émise. Il ne constitue pas un remboursement.</flux:text>
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach
        <form method="GET" class="flex flex-wrap gap-3">
            <flux:input name="search" label="Numéro d’avoir, facture ou UUID public" :value="request('search')" />
            @unless ($ownerMode)
            <flux:select name="status" label="État"><option value="">Tous</option>
                @foreach (\App\Enums\Central\Billing\DocumentStatusEnum::cases() as $status)<option value="{{ $status->value }}" @selected((string) request('status') === (string) $status->value)>{{ $status->label() }}</option>@endforeach
            </flux:select>
            @endunless
            <flux:button type="submit">Rechercher</flux:button>
        </form>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm">
            <thead><tr><th class="p-3">Avoir</th><th class="p-3">Facture corrigée</th><th class="p-3">Commerçant</th><th class="p-3">Correction TTC</th><th class="p-3">État</th><th class="p-3">Actions</th></tr></thead>
            <tbody>@forelse ($credits as $credit)<tr class="border-t border-zinc-200 dark:border-zinc-700">
                <td class="p-3">{{ $credit->number ?? 'Brouillon' }}</td><td class="p-3">{{ $credit->originalInvoice->number }}</td>
                <td class="p-3">{{ $credit->owner?->name }}</td><td class="p-3">{{ $credit->total_amount }} {{ $credit->currency }}</td><td class="p-3">{{ $credit->status->label() }}</td>
                <td class="p-3"><flux:button size="sm" :href="route($ownerMode ? 'owner.credit-notes.show' : 'central.credit-notes.show', $credit)">Informations</flux:button></td>
            </tr>@empty<tr><td colspan="6" class="p-3">Aucun avoir disponible.</td></tr>@endforelse</tbody>
        </table></div>
        {{ $credits->links() }}
    </section>
</x-layouts::app>
