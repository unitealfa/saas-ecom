<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\Billing\DeliveryChannelEnum;
use App\Enums\Central\Billing\DeliveryStatusEnum;
use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\MediaVisibilityEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Billing\SaasCreditNote;
use App\Models\Central\Billing\SaasDocumentDelivery;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Media\Media;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\Central\Subscriptions\SubscriptionRequest;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use App\Services\Central\Media\CentralMediaService;
use App\Services\Central\Media\PrivateMediaAccessService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SaasInvoiceIssuanceService
{
    public const string EVENT_ISSUED = 'saas_invoice_issued';

    public const string EVENT_FAILED = 'saas_invoice_issuance_failed';

    public const string CREDIT_ISSUED = 'saas_credit_note_issued';

    public const string CREDIT_FAILED = 'saas_credit_note_issuance_failed';

    private const int LOCK_WAIT_SECONDS = 10;

    public function __construct(
        private readonly AuthorizationMutation $mutations,
        private readonly SaasNumberingService $numbering,
        private readonly SaasInvoicePdfService $pdf,
        private readonly PrivateMediaAccessService $files,
        private readonly InvoiceCalculator $calculator,
        private readonly SaasCreditNoteService $credits,
        private readonly InvoicePaymentService $payments,
    ) {}

    public function issue(User $actor, SaasInvoice $invoice, string $expected): SaasInvoice
    {
        return $this->emit($actor, $invoice, $expected, 'issue');
    }

    public function issueForRequest(User $actor, SaasInvoice $invoice, string $expected): SaasInvoice
    {
        return $this->emit($actor, $invoice, $expected, 'issueForRequest');
    }

    private function emit(User $actor, SaasInvoice $invoice, string $expected, string $ability): SaasInvoice
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize($ability, $invoice);
        $lock = 'invoice:'.hash('sha256', DB::connection()->getDatabaseName().':'.$invoice->uuid);
        $lock = substr($lock, 0, 64);
        if ((int) DB::selectOne('SELECT GET_LOCK(?, ?) AS acquired', [$lock, self::LOCK_WAIT_SECONDS])->acquired !== 1) {
            $this->fail('Une émission est déjà en cours. Attendez puis rechargez cette facture.');
        }
        try {
            $current = $invoice->newQuery()->whereKey($invoice->id)->firstOrFail();
            Gate::forUser($actor)->authorize($ability, $current);
            if ($current->status === DocumentStatusEnum::ISSUED) {
                $this->assertMedia($current);

                return $current;
            }
            if ($current->saas_identity_snapshot['preparation_only'] ?? false) {
                $this->fail('Cette préparation de démonstration ne peut pas être émise : elle ne contient pas les identités et paramètres fiscaux validés nécessaires.');
            }
            $current = $ability === 'issueForRequest'
                ? $this->numbering->reserveRequestNumber($actor, $current, $expected, $this->assertIssuable(...))
                : $this->numbering->reserveNextNumber($actor, $current, $expected, $this->assertIssuable(...));
            $this->assertIssuable($current);
            $file = $this->pdf->generate($current);

            return $this->finalize($actor, $current, $file, $ability);
        } catch (Throwable $exception) {
            activity('central')->causedBy($actor)->performedOn($invoice)->event($invoice instanceof SaasCreditNote ? self::CREDIT_FAILED : self::EVENT_FAILED)
                ->tap(fn (Activity $activity) => $activity->forceFill(['correlation_id' => $invoice->getAttribute('correlation_id')]))
                ->withProperties([$invoice instanceof SaasCreditNote ? 'credit_uuid' : 'invoice_uuid' => $invoice->uuid,
                    'error_code' => $invoice instanceof SaasCreditNote ? 'CREDIT_NOTE_ISSUANCE_FAILED' : 'INVOICE_ISSUANCE_FAILED'])
                ->log('Émission non finalisée. Une reprise conserve le document et son numéro.');
            if ($exception instanceof ValidationException || $exception instanceof AuthorizationException) {
                throw $exception;
            }
            report($exception);
            $this->fail('L’émission n’a pas abouti. Le document et son éventuel numéro sont conservés. Réessayez après correction du problème technique.');
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
        }
    }

    /** @param array{storage_key: string, file_hash: string, size_bytes: int} $file */
    private function finalize(User $actor, SaasInvoice $invoice, array $file, string $ability): SaasInvoice
    {
        return $this->mutations->run($actor, $ability, function () use ($invoice): SaasInvoice {
            User::withTrashed()->whereKey($invoice->user_id)->lockForUpdate()->firstOrFail();
            Subscription::whereKey($invoice->subscription_id)->lockForUpdate()->firstOrFail();
            SubscriptionInstallment::whereKey($invoice->installment_id)->lockForUpdate()->firstOrFail();

            return $invoice->newQuery()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
        }, function (SaasInvoice $current) use ($actor, $file): SaasInvoice {
            if ($current->status === DocumentStatusEnum::ISSUED) {
                $this->assertMedia($current);

                return $current;
            }
            if ($current->status !== DocumentStatusEnum::PREPARING) {
                $this->fail('Cette facture ne peut pas être émise dans son état actuel.');
            }
            $this->assertIssuable($current);
            $bytes = Storage::disk(CentralMediaService::DISK)->get($file['storage_key']);
            if (! hash_equals($file['file_hash'], hash('sha256', $bytes))) {
                $this->fail('Le PDF a changé avant sa finalisation. Aucune émission enregistrée.');
            }
            $media = new Media;
            $media->forceFill([...$file, 'model_type' => $current->getMorphClass(), 'model_id' => $current->id,
                'created_by_id' => $actor->id, 'collection_name' => SaasInvoicePdfService::COLLECTION,
                'disk' => CentralMediaService::DISK, 'original_name' => $current->number.'.pdf',
                'mime_type' => 'application/pdf', 'visibility' => MediaVisibilityEnum::PRIVATE, 'is_primary' => true, 'position' => 1])->save();
            $current->forceFill(['document_media_id' => $media->id, 'status' => DocumentStatusEnum::ISSUED, 'issued_at' => now()])->save();
            if ($current instanceof SaasCreditNote) {
                $this->payments->synchronizeInstallment($actor, $current->originalInvoice);
            }
            $recipient = $current instanceof SaasCreditNote ? $current->owner->email : $current->customer_identity_snapshot['email'];
            if ($current instanceof SaasCreditNote && ! $current->owner->hasVerifiedEmail()) {
                $this->fail('L’adresse actuelle du destinataire doit être vérifiée avant l’émission de l’avoir.');
            }
            (new SaasDocumentDelivery)->forceFill([
                'user_id' => $current->user_id, 'document_id' => $current->id, 'created_by_id' => $actor->id,
                'document_type' => $current->document_type, 'channel' => DeliveryChannelEnum::EMAIL,
                'encrypted_recipient' => $recipient, 'delivery_status' => DeliveryStatusEnum::PENDING,
                'attempts_count' => 0, 'operation_key' => 'saas:delivery:'.$current->uuid.':email:'.hash('sha256', $recipient).':initial',
                'correlation_id' => $current->getAttribute('correlation_id'),
            ])->save();
            activity('central')->causedBy($actor)->performedOn($current)->event($current instanceof SaasCreditNote ? self::CREDIT_ISSUED : self::EVENT_ISSUED)
                ->tap(fn (Activity $activity) => $activity->forceFill(['correlation_id' => $current->getAttribute('correlation_id')]))
                ->withProperties(['invoice_uuid' => $current->uuid, 'number' => $current->number,
                    'amount' => $current->total_amount, 'currency' => $current->currency, 'media_uuid' => $media->uuid])
                ->log('Document émis et intention de transmission enregistrée, sans preuve d’envoi.');

            return $current->refresh();
        });
    }

    public function download(User $actor, SaasInvoice $invoice, bool $inline = false): StreamedResponse
    {
        abort_if(tenancy()->initialized, 403);
        $current = $invoice->newQuery()->whereKey($invoice->id)->firstOrFail();
        Gate::forUser($actor)->authorize('download', $current);
        $media = $this->assertMedia($current);

        $response = $this->files->download($actor, $media);
        if ($inline) {
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition('inline', 'facture-'.$current->uuid.'.pdf'));
        }

        return $response;
    }

    private function assertMedia(SaasInvoice $invoice): Media
    {
        $media = Media::whereKey($invoice->getAttribute('document_media_id'))->firstOrFail();
        if ($media->model_type !== $invoice->getMorphClass() || $media->model_id !== $invoice->id
            || $media->collection_name !== SaasInvoicePdfService::COLLECTION
            || $media->visibility !== MediaVisibilityEnum::PRIVATE || $media->disk !== CentralMediaService::DISK
            || $media->storage_key !== $this->pdf->storageKey($invoice)
            || ! Storage::disk($media->disk)->exists($media->storage_key)
            || ! hash_equals((string) $media->file_hash, hash('sha256', Storage::disk($media->disk)->get($media->storage_key)))) {
            $this->fail('L’intégrité du PDF privé ne peut pas être confirmée.');
        }

        return $media;
    }

    private function assertIssuable(SaasInvoice $invoice): void
    {
        $identity = ['name' => ['required', 'string'], 'address' => ['required', 'string'], 'nif' => ['required', 'string'],
            'validation_reference' => ['required', 'string'], 'validated_at' => ['required', 'date', 'before_or_equal:now']];
        Validator::make($invoice->saas_identity_snapshot, $identity)->validate();
        Validator::make($invoice->customer_identity_snapshot, [...$identity, 'email' => ['required', 'email']])->validate();
        $policy = $invoice->billing_rule_snapshot['fiscal_policy'] ?? [];
        Validator::make($policy, ['validation_reference' => ['required', 'string'], 'validated_at' => ['required', 'date', 'before_or_equal:now'],
            'rates' => ['present', 'array'], 'no_tax_reason' => empty($policy['rates']) ? ['required', 'string'] : ['nullable', 'string']])->validate();
        if ($invoice->currency !== SaasInvoice::CURRENCY || $invoice->period_ends_at->lessThanOrEqualTo($invoice->period_starts_at)) {
            $this->fail('La devise ou la période de la facture est invalide.');
        }
        if ($invoice instanceof SaasCreditNote) {
            $this->credits->assertIssuable($invoice);

            return;
        }
        $requestId = $invoice->subscription->getAttribute('subscription_request_id');
        if ($requestId !== null) {
            $request = SubscriptionRequest::whereKey($requestId)->lockForUpdate()->firstOrFail();
            if ($request->status->isFinal() || $request->current_subscription_id !== $invoice->subscription_id || $request->current_invoice_id !== $invoice->id) {
                $this->fail('Cette facture ne correspond plus à la demande courante.');
            }
        }
        if ($invoice->status === DocumentStatusEnum::DRAFT) {
            $rule = $invoice->billingRule;
            BillingRule::where('code', $rule->code)->orderBy('version')->lockForUpdate()->firstOrFail();
            $rule = BillingRule::whereKey($rule->id)->lockForUpdate()->firstOrFail();
            if (! $rule->isActiveAt(now())) {
                $this->fail('La règle de facturation n’est plus applicable.');
            }
        }
        $installment = $invoice->installment;
        if ($installment->user_id !== $invoice->user_id || $installment->subscription_id !== $invoice->subscription_id
            || ! BigDecimal::of($invoice->total_amount)->isEqualTo($installment->installment_amount)
            || ! $invoice->period_starts_at->equalTo($installment->period_starts_at)
            || ! $invoice->period_ends_at->equalTo($installment->period_ends_at)) {
            $this->fail('La facture ne correspond pas à son échéance et à son propriétaire.');
        }
        $lines = $invoice->lines()->orderBy('id')->get();
        if ($lines->isEmpty()) {
            $this->fail('Une facture doit contenir au moins une ligne.');
        }
        $inputs = array_values($lines->map(fn ($line): array => $line->only(['description', 'quantity', 'net_unit_price', 'net_discount']))->all());
        $amounts = count($inputs) === 1 && $inputs[0]['quantity'] === '1.00' && $inputs[0]['net_discount'] === '0.00'
            ? $this->calculator->fromTotal($invoice->total_amount, $inputs[0]['description'], $policy['rates'])
            : $this->calculator->calculate($inputs, $policy['rates']);
        if ($amounts['taxes'] !== $invoice->getAttribute('taxes')) {
            $this->fail('Les taxes ne correspondent pas à la politique fiscale validée.');
        }
        foreach ($lines as $index => $line) {
            if ($line->getAttribute('taxes') !== $amounts['lines'][$index]['taxes']) {
                $this->fail('La ventilation fiscale de la ligne est incohérente.');
            }
        }
        $sums = ['net_amount' => BigDecimal::of('0.00'), 'tax_amount' => BigDecimal::of('0.00'), 'total_amount' => BigDecimal::of('0.00')];
        foreach ($lines as $line) {
            if (BigDecimal::of($line->quantity)->isLessThanOrEqualTo(0)
                || ! BigDecimal::of($line->quantity)->multipliedBy($line->net_unit_price)->minus($line->getAttribute('net_discount'))
                    ->toScale(2, RoundingMode::HalfUp)->isEqualTo($line->net_amount)
                || ! BigDecimal::of($line->net_amount)->plus($line->tax_amount)->isEqualTo($line->total_amount)) {
                $this->fail('Les montants des lignes sont incohérents.');
            }
            $tax = BigDecimal::of('0.00');
            foreach ($line->getAttribute('taxes') as $item) {
                Validator::make($item, ['code' => ['required', 'string'], 'rate' => ['required', 'string', 'numeric', 'min:0', 'max:100'],
                    'amount' => ['required', 'string', 'regex:/^\d{1,12}\.\d{2}$/D']])->validate();
                $tax = $tax->plus($item['amount']);
            }
            if (! $tax->isEqualTo($line->tax_amount)) {
                $this->fail('La ventilation des taxes ne correspond pas à la ligne.');
            }
            foreach ($sums as $field => $sum) {
                $amount = BigDecimal::of($line->getAttribute($field));
                if ($amount->isNegative()) {
                    $this->fail('Un montant de facture ne peut pas être négatif.');
                }
                $sums[$field] = $sum->plus($amount);
            }
        }
        foreach ($sums as $field => $sum) {
            if (! $sum->isEqualTo($invoice->getAttribute($field))) {
                $this->fail('Les totaux de la facture ne correspondent pas aux lignes.');
            }
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['invoice' => $message]);
    }
}
