@if ($document->status === \App\Enums\Central\Billing\DocumentStatusEnum::ISSUED)
    <section class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:heading level="2">Envoi du document</flux:heading>
        @can('send', \App\Models\Central\Billing\SaasDocumentDelivery::class)
            <form method="POST" action="{{ route('central.deliveries.send') }}">
                @csrf
                <input type="hidden" name="document_uuid" value="{{ $document->uuid }}">
                <flux:button type="submit">Envoyer le document par e-mail</flux:button>
            </form>
        @endcan
        @can('viewAny', \App\Models\Central\Billing\SaasDocumentDelivery::class)
            @forelse ($document->deliveries as $delivery)
                <div class="space-y-2">
                    <flux:text>Statut d’envoi : {{ $delivery->delivery_status->label() }} — {{ $delivery->attempts_count }} tentative(s).</flux:text>
                    @if ($delivery->sent_at)<flux:text>Dernière acceptation SMTP : {{ $delivery->sent_at->format('d/m/Y H:i') }}. Elle ne prouve ni la remise ni la lecture.</flux:text>@endif
                    @foreach (['retry' => 'Relancer après échec', 'resend' => 'Renvoyer le document'] as $ability => $label)
                        @can($ability, $delivery)
                            <form method="POST" action="{{ route('central.deliveries.'.$ability, $delivery) }}">
                                @csrf
                                <input type="hidden" name="expected_state" value="{{ $delivery->managementState() }}">
                                <flux:button type="submit">{{ $label }}</flux:button>
                            </form>
                        @endcan
                    @endforeach
                    @foreach ($delivery->delivery_attempts ?? [] as $attempt)
                        <flux:text>Tentative {{ $loop->iteration }} — {{ $attempt['started_at'] ?? '' }} — {{ $attempt['status'] ?? '' }}</flux:text>
                    @endforeach
                </div>
            @empty
                <flux:text>Aucune intention d’envoi enregistrée. Le document doit être vérifié avant toute transmission.</flux:text>
            @endforelse
        @endcan
    </section>
@endif
