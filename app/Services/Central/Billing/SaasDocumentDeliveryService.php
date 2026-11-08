<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\Billing\DeliveryChannelEnum;
use App\Enums\Central\Billing\DeliveryStatusEnum;
use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Enums\Central\Users\StatusEnum as UserStatus;
use App\Enums\MediaVisibilityEnum;
use App\Jobs\Central\SendSaasDocumentJob;
use App\Models\Central\Billing\SaasCreditNote;
use App\Models\Central\Billing\SaasDocumentDelivery;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Media\Media;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use App\Services\Central\Media\CentralMediaService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SaasDocumentDeliveryService
{
    private const int MAX_ATTEMPTS = 3;

    private const int MAX_PDF_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private readonly AuthorizationMutation $mutations,
        private readonly SaasInvoicePdfService $pdf,
    ) {}

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, SaasDocumentDelivery>
     */
    public function search(User $actor, array $filters): LengthAwarePaginator
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize('viewAny', SaasDocumentDelivery::class);

        return SaasDocumentDelivery::with(['document', 'owner', 'creator'])
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('delivery_status', $filters['status']))
            ->when(! empty($filters['document_uuid']), fn (Builder $query) => $query->whereHas('document',
                fn (Builder $document) => $document->where('uuid', $filters['document_uuid'])))
            ->orderByDesc('id')->paginate(20)->withQueryString();
    }

    /** Reprend l'intention créée lors de l'émission, jamais un second envoi implicite. */

    /**
     * Programme une seule tâche pour une intention en attente.
     * next_attempt_at sert de limite si le worker ne récupère pas le job.
     */
    public function request(User $actor, string $documentUuid): SaasDocumentDelivery
    {
        abort_if(tenancy()->initialized, 403);

        [$delivery, $shouldDispatch] = $this->mutations->run(
            $actor,
            'send',
            fn () => SaasDocumentDelivery::class,
            function () use ($actor, $documentUuid): array {
                $document = $this->findDocument($documentUuid, lock: true);
                $recipient = $this->assertRecipient($document);

                $existing = SaasDocumentDelivery::where('document_id', $document->id)
                    ->where('channel', DeliveryChannelEnum::EMAIL->value)
                    ->lockForUpdate()
                    ->get();

                foreach ($existing as $row) {
                    if ($row->encrypted_recipient !== $recipient) {
                        $this->fail('Le destinataire historique est différent. Vérifiez le dossier avant toute transmission.');
                    }
                }

                if ($existing->count() > 1) {
                    $this->fail('Plusieurs intentions existent : vérification administrative nécessaire.');
                }

                $delivery = $existing->first();

                if ($delivery === null) {
                    $this->fail(
                        'Aucune intention d’envoi existante. '
                        .'Le document doit disposer d’une intention créée lors de son émission.'
                    );
                }

                if (! in_array($delivery->delivery_status, [
                    DeliveryStatusEnum::PENDING,
                    DeliveryStatusEnum::SENDING,
                    DeliveryStatusEnum::SENT,
                    DeliveryStatusEnum::DELIVERED,
                ], true)) {
                    $this->fail(
                        'Cette intention ne peut pas être redemandée. '
                        .'Utilisez la relance autorisée.'
                    );
                }

                $shouldDispatch =
                    $delivery->delivery_status === DeliveryStatusEnum::PENDING
                    && $delivery->next_attempt_at === null;

                if ($shouldDispatch) {
                    $delivery->forceFill([
                        'next_attempt_at' => now()->addMinutes(10),
                    ])->save();

                    $this->audit(
                        $actor,
                        $delivery,
                        'saas_delivery_requested',
                        'Envoi demandé ; aucune transmission encore confirmée.'
                    );
                }

                return [$delivery, $shouldDispatch];
            },
        );

        if ($shouldDispatch) {
            SendSaasDocumentJob::dispatch($delivery->id)->afterCommit();
        }

        return $delivery;
    }

    public function retry(User $actor, SaasDocumentDelivery $delivery, string $expectedState): SaasDocumentDelivery
    {
        abort_if(tenancy()->initialized, 403);
        $delivery = $this->mutations->run($actor, 'retry',
            fn () => SaasDocumentDelivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail(),
            function (SaasDocumentDelivery $current) use ($actor, $expectedState): SaasDocumentDelivery {
                if (! hash_equals($current->managementState(), $expectedState)) {
                    $this->fail('L’état de cet envoi a changé. Rechargez la page.');
                }
                if (! in_array($current->delivery_status, [DeliveryStatusEnum::RETRYABLE, DeliveryStatusEnum::FAILED], true)
                    || $current->attempts_count >= self::MAX_ATTEMPTS || $current->channel !== DeliveryChannelEnum::EMAIL) {
                    $this->fail('La relance n’est pas autorisée pour cet état. Un résultat incertain doit être vérifié manuellement.');
                }
                $this->assertRecipient($this->documentById($current));
                $current->forceFill([
                    'delivery_status' => DeliveryStatusEnum::PENDING,
                    'error_code' => null,
                    'next_attempt_at' => now()->addMinutes(10),
                ])->save();
                $this->audit($actor, $current, 'saas_delivery_retried', 'Relance demandée après échec identifié.');

                return $current;
            });

        SendSaasDocumentJob::dispatch($delivery->id)->afterCommit();

        return $delivery;
    }

    /** Exécuté par le worker, pas par une requête HTTP. Aucun envoi automatique d'un état incertain. */
    public function process(int $id): void
    {
        if (tenancy()->initialized) {
            throw new \LogicException('SaaS document delivery requires the central database.');
        }
        $claimed = DB::transaction(function () use ($id): ?array {
            $delivery = SaasDocumentDelivery::whereKey($id)->lockForUpdate()->first();
            if ($delivery === null || $delivery->delivery_status !== DeliveryStatusEnum::PENDING) {
                return null;
            }
            if ($delivery->attempts_count >= self::MAX_ATTEMPTS) {
                $delivery->forceFill(['delivery_status' => DeliveryStatusEnum::FAILED, 'error_code' => 'ATTEMPT_LIMIT'])->save();

                return null;
            }
            $token = (string) Str::uuid();
            $attempts = $delivery->delivery_attempts ?? [];
            $attempts[] = ['id' => $token, 'started_at' => now()->toISOString(), 'status' => 'sending'];
            $delivery->forceFill([
                'attempts_count' => $delivery->attempts_count + 1,
                'delivery_attempts' => $attempts,
                'delivery_status' => DeliveryStatusEnum::SENDING,
                'sending_started_at' => now(),
                'next_attempt_at' => null,
                'error_code' => null,
            ])->save();

            return [$delivery->id, $token];
        }, 3);

        if ($claimed === null) {
            return;
        }
        [$deliveryId, $token] = $claimed;
        try {
            $delivery = SaasDocumentDelivery::findOrFail($deliveryId);
            $document = $this->documentById($delivery);
            $recipient = $this->assertRecipient($document);
            if ($delivery->encrypted_recipient !== $recipient || $delivery->channel !== DeliveryChannelEnum::EMAIL) {
                throw new \LogicException('The delivery recipient/channel no longer matches the verified document owner.');
            }
            $bytes = $this->verifiedPdf($document);
            if (config('mail.default') !== 'smtp') {
                $this->complete($deliveryId, $token, DeliveryStatusEnum::RETRYABLE, 'SMTP_NOT_CONFIGURED');

                return;
            }
        } catch (Throwable $exception) {
            $this->complete($deliveryId, $token, DeliveryStatusEnum::RETRYABLE, 'DOCUMENT_OR_RECIPIENT_UNAVAILABLE');
            Log::warning('SaaS delivery preparation failed.', ['delivery_id' => $deliveryId, 'exception_class' => $exception::class]);

            return;
        }

        // La réponse SMTP prouve l'acceptation du transport, pas la remise dans la boîte du destinataire.
        try {
            Mail::raw('Veuillez trouver votre document de facturation Aydra en pièce jointe. Aucun paiement n’est demandé par cet e-mail.',
                function ($message) use ($recipient, $document, $bytes): void {
                    $name = preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $document->number) ?: $document->uuid;
                    $message->to($recipient)
                        ->subject('Aydra — '.$document->document_type->label().' '.$document->number)
                        ->attachData($bytes, $name.'.pdf', ['mime' => 'application/pdf']);
                });
        } catch (Throwable $exception) {
            // Un SMTP peut avoir accepté le message avant de renvoyer une erreur : aucun renvoi automatique.
            $this->complete($deliveryId, $token, DeliveryStatusEnum::UNCERTAIN, 'SMTP_RESULT_UNCERTAIN');
            Log::warning('SaaS email result uncertain.', ['delivery_id' => $deliveryId, 'exception_class' => $exception::class]);

            return;
        }
        $this->complete($deliveryId, $token, DeliveryStatusEnum::SENT, null);
    }

    private function complete(
        int $id,
        string $token,
        DeliveryStatusEnum $status,
        ?string $errorCode
    ): void {
        DB::transaction(function () use ($id, $token, $status, $errorCode): void {
            $delivery = SaasDocumentDelivery::whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $attempts = $delivery->delivery_attempts ?? [];
            $last = array_key_last($attempts);

            if (
                $delivery->delivery_status !== DeliveryStatusEnum::SENDING
                || $last === null
                || ($attempts[$last]['id'] ?? '') !== $token
            ) {
                throw new \LogicException(
                    'Delivery attempt changed before recording the outcome.'
                );
            }

            $finalStatus = (
                $status === DeliveryStatusEnum::RETRYABLE
                && $delivery->attempts_count >= self::MAX_ATTEMPTS
            )
                ? DeliveryStatusEnum::FAILED
                : $status;

            $attempts[$last]['status'] = strtolower($finalStatus->name);
            $attempts[$last]['finished_at'] = now()->toISOString();

            $delivery->forceFill([
                'delivery_status' => $finalStatus,
                'delivery_attempts' => $attempts,
                'error_code' => $errorCode,
                'sent_at' => $finalStatus === DeliveryStatusEnum::SENT
                    ? now()
                    : $delivery->sent_at,
                'sending_started_at' => null,
            ])->save();

            activity('central')
                ->performedOn($delivery)
                ->event('saas_delivery_'.strtolower($finalStatus->name))
                ->withProperties([
                    'delivery_uuid' => $delivery->uuid,
                    'document_type' => $delivery->document_type->value,
                    'attempts_count' => $delivery->attempts_count,
                    'error_code' => $errorCode,
                ])
                ->log('Résultat de la transmission enregistré sans divulguer le destinataire.');
        }, 3);
    }

    /**
     * Réconcilie les envois qui n'ont jamais été terminés.
     * Aucun e-mail n'est envoyé par cette méthode.
     */
    public function reconcileStale(): int
    {
        if (tenancy()->initialized) {
            throw new \LogicException(
                'SaaS document delivery requires the central database.'
            );
        }

        $now = CarbonImmutable::now();
        $cutoff = $now->subMinutes(10);

        $ids = SaasDocumentDelivery::query()
            ->where(function (Builder $query) use ($cutoff, $now): void {
                $query->where(function (Builder $query) use ($cutoff): void {
                    $query->where(
                        'delivery_status',
                        DeliveryStatusEnum::SENDING->value
                    )->where('sending_started_at', '<=', $cutoff);
                })->orWhere(function (Builder $query) use ($now): void {
                    $query->where(
                        'delivery_status',
                        DeliveryStatusEnum::PENDING->value
                    )->whereNotNull('next_attempt_at')
                        ->where('next_attempt_at', '<=', $now);
                });
            })
            ->orderBy('id')
            ->limit(100)
            ->pluck('id');

        $count = 0;

        foreach ($ids as $id) {
            DB::transaction(function () use (
                $id,
                $cutoff,
                $now,
                &$count
            ): void {
                $delivery = SaasDocumentDelivery::whereKey($id)
                    ->lockForUpdate()
                    ->first();

                if ($delivery === null) {
                    return;
                }

                $sending = (
                    $delivery->delivery_status === DeliveryStatusEnum::SENDING
                    && $delivery->sending_started_at !== null
                    && $delivery->sending_started_at->lessThanOrEqualTo($cutoff)
                );

                $pending = (
                    $delivery->delivery_status === DeliveryStatusEnum::PENDING
                    && $delivery->next_attempt_at !== null
                    && $delivery->next_attempt_at->lessThanOrEqualTo($now)
                );

                if (! $sending && ! $pending) {
                    return;
                }

                $attempts = $delivery->delivery_attempts ?? [];

                if ($sending) {
                    $last = array_key_last($attempts);

                    if (
                        $last !== null
                        && ($attempts[$last]['status'] ?? '') === 'sending'
                    ) {
                        $attempts[$last]['status'] = 'uncertain';
                        $attempts[$last]['finished_at'] = now()->toISOString();
                    }
                }

                $status = $sending
                    ? DeliveryStatusEnum::UNCERTAIN
                    : DeliveryStatusEnum::RETRYABLE;

                $code = $sending
                    ? 'WORKER_RESULT_UNKNOWN'
                    : 'QUEUE_NOT_PROCESSED';

                $delivery->forceFill([
                    'delivery_status' => $status,
                    'delivery_attempts' => $attempts,
                    'error_code' => $code,
                    'sending_started_at' => null,
                    'next_attempt_at' => null,
                ])->save();

                activity('central')
                    ->performedOn($delivery)
                    ->event('saas_delivery_reconciled')
                    ->withProperties([
                        'delivery_uuid' => $delivery->uuid,
                        'error_code' => $code,
                    ])
                    ->log('Envoi non achevé réconcilié sans nouvelle tentative automatique.');

                $count++;
            }, 3);
        }

        return $count;
    }

    private function findDocument(string $uuid, bool $lock = false): SaasInvoice
    {
        $invoice = SaasInvoice::where('uuid', $uuid);
        if ($lock) {
            $invoice->lockForUpdate();
        }
        $document = $invoice->first();
        if ($document === null) {
            $credits = SaasCreditNote::where('uuid', $uuid);
            if ($lock) {
                $credits->lockForUpdate();
            }
            $document = $credits->firstOrFail();
        }
        if ($document->status !== DocumentStatusEnum::ISSUED || $document->getAttribute('document_media_id') === null) {
            $this->fail('Seul un document définitivement émis et conservé peut être envoyé.');
        }

        return $document;
    }

    private function documentById(SaasDocumentDelivery $delivery): SaasInvoice
    {
        $document = $delivery->document_type === DocumentTypeEnum::CREDIT_NOTE
            ? SaasCreditNote::whereKey($delivery->document_id)->firstOrFail()
            : SaasInvoice::whereKey($delivery->document_id)->firstOrFail();
        if ($document->status !== DocumentStatusEnum::ISSUED || $document->user_id !== $delivery->user_id
            || $document->document_type !== $delivery->document_type) {
            throw new \LogicException('Delivery references an invalid or unissued document.');
        }

        return $document;
    }

    private function assertRecipient(SaasInvoice $document): string
    {
        $owner = User::whereKey($document->user_id)->first();
        if ($owner === null || $owner->status !== UserStatus::ACTIVE || ! $owner->hasVerifiedEmail()) {
            $this->fail('Le compte destinataire doit être actif avec une adresse e-mail vérifiée.');
        }
        $recipient = trim((string) $owner->email);
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->fail('Adresse e-mail du propriétaire invalide.');
        }
        if ($document->document_type === DocumentTypeEnum::INVOICE) {
            $historical = trim((string) ($document->customer_identity_snapshot['email'] ?? ''));
            if (strcasecmp($historical, $recipient) !== 0) {
                $this->fail('L’adresse historique de la facture a changé. Vérification administrative nécessaire.');
            }
        }

        return $recipient;
    }

    private function verifiedPdf(SaasInvoice $document): string
    {
        $media = Media::whereKey($document->getAttribute('document_media_id'))->firstOrFail();
        if ($media->model_type !== $document->getMorphClass() || $media->model_id !== $document->id
            || $media->collection_name !== SaasInvoicePdfService::COLLECTION
            || $media->visibility !== MediaVisibilityEnum::PRIVATE
            || $media->disk !== CentralMediaService::DISK || $media->mime_type !== 'application/pdf'
            || $media->storage_key !== $this->pdf->storageKey($document)
            || $media->size_bytes <= 0 || $media->size_bytes > self::MAX_PDF_BYTES) {
            throw new \LogicException('Private document metadata cannot be verified.');
        }
        $disk = Storage::disk(CentralMediaService::DISK);
        if (! $disk->exists($media->storage_key)) {
            throw new \LogicException('Private PDF is missing.');
        }
        $bytes = $disk->get($media->storage_key);
        if (strlen($bytes) !== $media->size_bytes || ! str_starts_with($bytes, '%PDF-')
            || ! is_string($media->file_hash) || ! hash_equals($media->file_hash, hash('sha256', $bytes))) {
            throw new \LogicException('Private PDF integrity verification failed.');
        }

        return $bytes;
    }

    private function audit(User $actor, SaasDocumentDelivery $delivery, string $event, string $message): void
    {
        activity('central')->causedBy($actor)->performedOn($delivery)->event($event)
            ->withProperties(['delivery_uuid' => $delivery->uuid, 'correlation_id' => $delivery->correlation_id])
            ->log($message);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['document_uuid' => $message]);
    }
}
