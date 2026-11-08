<x-layouts::app title="Facturation — Envois">
    <section class="mx-auto flex w-full max-w-6xl flex-col gap-6">
        <flux:heading size="xl" level="1">Facturation — Envois</flux:heading>
        <flux:text>
            Les factures et avoirs restent consultables sur la plateforme. L'envoi par e-mail est une opération
            distincte. « Accepté par le serveur e-mail » ne signifie pas « lu » ou « remis ».
            SMS et WhatsApp ne sont pas activés sans fournisseur configuré.
        </flux:text>
        @if (session('status'))<flux:callout>{{ session('status') }}</flux:callout>@endif
        @foreach ($errors->all() as $error)<flux:text role="alert">{{ $error }}</flux:text>@endforeach

        @can('send', \App\Models\Central\Billing\SaasDocumentDelivery::class)
            <form method="POST" action="{{ route('central.deliveries.send') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <flux:input name="document_uuid" label="UUID de la facture ou de l'avoir émis"
                    :value="old('document_uuid', request('document_uuid'))" required />
                <flux:button type="submit" variant="primary">Demander l'envoi e-mail</flux:button>
            </form>
        @endcan

        <form method="GET" action="{{ route('central.deliveries.index') }}" class="flex flex-wrap items-end gap-3">
            <flux:input name="document_uuid" label="UUID du document" :value="request('document_uuid')" />
            <flux:select name="status" label="État">
                <option value="">Tous les états</option>
                @foreach (\App\Enums\Central\Billing\DeliveryStatusEnum::cases() as $status)
                    <option value="{{ $status->value }}" @selected((string) request('status') === (string) $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </flux:select>
            <flux:button type="submit">Filtrer</flux:button>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead><tr>
                    <th class="p-3">Document</th><th class="p-3">Destinataire</th>
                    <th class="p-3">Canal</th><th class="p-3">État</th>
                    <th class="p-3">Tentatives</th><th class="p-3">Créé le</th><th class="p-3">Action</th>
                </tr></thead>
                <tbody>
                    @forelse ($deliveries as $delivery)
                        <tr class="border-t border-zinc-200 dark:border-zinc-700">
                            <td class="p-3">{{ $delivery->document?->number ?? $delivery->document?->uuid ?? 'Document indisponible' }}</td>
                            <td class="p-3">{{ $delivery->owner?->name ?? 'Compte archivé' }}</td>
                            <td class="p-3">{{ $delivery->channel->label() }}</td>
                            <td class="p-3">{{ $delivery->delivery_status->label() }}</td>
                            <td class="p-3">{{ $delivery->attempts_count }}</td>
                            <td class="p-3">{{ $delivery->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="p-3"><flux:button size="sm" :href="route('central.deliveries.show', $delivery)">Détails</flux:button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-3">Aucun envoi enregistré pour ces filtres.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $deliveries->links() }}
    </section>
</x-layouts::app>
