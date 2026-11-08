<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\TransferMethodEnum;
use App\Enums\Central\Billing\TransferStatusEnum;
use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Enums\MediaVisibilityEnum;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Media\Media;
use App\Models\Central\Users\User;
use App\Services\Central\Media\CentralMediaService;
use App\Services\Central\Media\PrivateMediaAccessService;
use Closure;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PaymentDocumentService
{
    public const string PROOF = 'payment_proof';

    public const string SOURCE = 'payment_source';

    public const string RECEIPT = 'payment_receipt';

    public const int MAX_KB = 8192;

    private const int MAX_PIXELS = 12000000;

    public function __construct(private readonly PrivateMediaAccessService $files) {}

    /** @return array{original: string, mime: string, pdf: string} */
    public function prepareExternalProof(UploadedFile $file): array
    {
        Validator::make(['proof' => $file], ['proof' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:'.self::MAX_KB]])->validate();
        $bytes = $file->getContent();
        $mime = (string) $file->getMimeType();
        if ($mime !== 'application/pdf') {
            $dimensions = @getimagesizefromstring($bytes);
            if ($dimensions === false || $dimensions[0] * $dimensions[1] > self::MAX_PIXELS) {
                $this->fail('L’image est invalide ou dépasse douze millions de pixels.');
            }
            $image = @imagecreatefromstring($bytes);
            if ($image === false) {
                $this->fail('L’image ne peut pas être décodée.');
            }
            ob_start();
            imagepng($image);
            $png = ob_get_clean();
            $bytes = $this->render('<img style="max-width:100%" src="data:image/png;base64,'.base64_encode($png).'">');
        }
        if (! str_starts_with($bytes, '%PDF-') || ! str_contains(substr($bytes, -1024), '%%EOF')) {
            $this->fail('Le justificatif ne contient pas un PDF valide.');
        }

        return ['original' => $file->getContent(), 'mime' => $mime, 'pdf' => $bytes];
    }

    /** @param array{original: string, mime: string, pdf: string} $prepared */
    public function attachExternalProof(User $actor, SaasPayment $payment, array $prepared): void
    {
        $this->withProofLock($payment, function () use ($actor, $payment, $prepared): void {
            $payment->refresh();
            $this->attachPreparedProof($actor, $payment, $prepared);
        });
    }

    /** @param array{original: string, mime: string, pdf: string} $prepared */
    private function attachPreparedProof(User $actor, SaasPayment $payment, array $prepared): void
    {
        $this->authorizeWriter($actor, $payment);
        if ($payment->getAttribute('proof_media_id') !== null) {
            $this->assertProof($payment);

            return;
        }
        $retainedSource = $payment->documents()->where('collection_name', self::SOURCE)->first();
        if ($retainedSource !== null && ! hash_equals((string) $retainedSource->file_hash, hash('sha256', $prepared['original']))) {
            $this->fail('La source originale est déjà conservée. Reprenez avec le même fichier, sans remplacer cette preuve.');
        }
        $source = $prepared['mime'] === 'application/pdf' ? null : $this->store($actor, $payment, self::SOURCE,
            $prepared['original'], $prepared['mime'], $prepared['mime'] === 'image/png' ? 'png' : 'jpg');
        $proof = $payment->documents()->where('collection_name', self::PROOF)->first();
        if ($proof !== null) {
            $this->assertFile($proof);
            if ($source === null && ! hash_equals((string) $proof->file_hash, hash('sha256', $prepared['pdf']))) {
                $this->fail('Le PDF original est déjà conservé. Reprenez avec le même fichier.');
            }
        } else {
            $proof = $this->store($actor, $payment, self::PROOF, $prepared['pdf'], 'application/pdf', 'pdf');
        }
        $this->attach($actor, $payment, $proof, $source);
    }

    public function prepareInternalProof(User $actor, SaasPayment $payment): void
    {
        $this->withProofLock($payment, function () use ($actor, $payment): void {
            $payment->refresh();
            $this->createInternalProof($actor, $payment);
        });
    }

    private function createInternalProof(User $actor, SaasPayment $payment): void
    {
        $this->authorizeWriter($actor, $payment);
        if ($payment->getAttribute('transfer_method') !== TransferMethodEnum::CASH_IN_PERSON) {
            $this->fail('Un versement externe exige son justificatif original, jamais un constat espèces.');
        }
        if ($payment->getAttribute('proof_media_id') !== null) {
            $this->assertProof($payment);

            return;
        }
        $proof = $payment->documents()->where('collection_name', self::PROOF)->first();
        if ($proof === null) {
            $bytes = $this->render(view('central.documents.payment-receipt', ['payment' => $payment->load('invoice'), 'confirmed' => false])->render());
            $proof = $this->store($actor, $payment, self::PROOF, $bytes, 'application/pdf', 'pdf');
        } else {
            $this->assertFile($proof);
        }
        $this->attach($actor, $payment, $proof, null);
    }

    public function assertProof(SaasPayment $payment): Media
    {
        $proof = Media::whereKey($payment->getAttribute('proof_media_id'))->first();
        if ($proof === null || $proof->model_type !== 'saas_payment' || $proof->model_id !== $payment->getKey()
            || $proof->collection_name !== self::PROOF || $proof->mime_type !== 'application/pdf') {
            $this->fail('Le justificatif privé est absent ou ne correspond pas au paiement.');
        }
        $this->assertFile($proof);
        if ($payment->getAttribute('source_proof_media_id') !== null) {
            $source = Media::whereKey($payment->getAttribute('source_proof_media_id'))->first();
            if ($source === null || $source->model_type !== 'saas_payment' || $source->model_id !== $payment->getKey() || $source->collection_name !== self::SOURCE) {
                $this->fail('La source du justificatif est introuvable.');
            }
            $this->assertFile($source);
        }

        return $proof;
    }

    public function generatePaymentReceipt(User $actor, SaasPayment $payment): Media
    {
        abort_if(tenancy()->initialized, 403);
        $payment = SaasPayment::whereKey($payment->getKey())->firstOrFail();
        $this->authorizeWriter($actor, $payment);
        $invoice = $payment->invoice;
        if ($payment->getAttribute('transfer_status') !== TransferStatusEnum::VERIFIED
            || $payment->getAttribute('amount') !== ($payment->getAttribute('encrypted_transfer_details')['settlement_total'] ?? $invoice->total_amount)
            || $payment->getAttribute('currency') !== $invoice->currency
            || $payment->getAttribute('user_id') !== $invoice->user_id || $payment->getAttribute('reversal_of_id') !== null
            || $invoice->status !== DocumentStatusEnum::ISSUED
            || $invoice->installment->installment_status !== InstallmentStatusEnum::PAID
            || SaasPayment::where('document_id', $invoice->id)->where('transfer_status', TransferStatusEnum::VERIFIED)->count() !== 1) {
            $this->fail('Un reçu exige un encaissement intégral vérifié.');
        }
        $this->assertProof($payment);
        $lock = 'receipt:'.substr(hash('sha256', DB::connection()->getDatabaseName().':'.$payment->getKey()), 0, 50);
        if ((int) DB::selectOne('SELECT GET_LOCK(?,10) AS acquired', [$lock])->acquired !== 1) {
            $this->fail('Le reçu est déjà en préparation. Réessayez.');
        }
        try {
            $existing = $payment->documents()->where('collection_name', self::RECEIPT)->first();
            if ($existing !== null) {
                $this->assertFile($existing);

                return $existing;
            }
            $bytes = $this->render(view('central.documents.payment-receipt', ['payment' => $payment->load('invoice'), 'confirmed' => true])->render());

            return $this->store($actor, $payment, self::RECEIPT, $bytes, 'application/pdf', 'pdf');
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
        }
    }

    public function download(User $actor, SaasPayment $payment, bool $receipt, bool $inline = false): StreamedResponse
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize($receipt ? 'receipt' : 'view', $payment);
        $media = $receipt ? $this->generatePaymentReceipt($actor, $payment) : $this->assertProof($payment);

        $response = $this->files->download($actor, $media);
        if ($receipt && $inline) {
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition('inline', 'recu-'.$payment->getAttribute('uuid').'.pdf'));
        }

        return $response;
    }

    private function attach(User $actor, SaasPayment $payment, Media $proof, ?Media $source): void
    {
        DB::transaction(function () use ($actor, $payment, $proof, $source): void {
            $current = SaasPayment::whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            $this->authorizeWriter($actor, $current);
            if ($current->getAttribute('proof_media_id') !== null) {
                return;
            }
            $current->forceFill(['proof_media_id' => $proof->id, 'source_proof_media_id' => $source?->id])->save();
        }, 3);
        $payment->refresh();
    }

    private function store(User $actor, SaasPayment $payment, string $collection, string $bytes, string $mime, string $extension): Media
    {
        $disk = Storage::disk(CentralMediaService::DISK);
        $key = CentralMediaService::DIRECTORY.'/payments/'.$payment->getAttribute('uuid').'/'.$collection.'/'.hash('sha256', $bytes).'.'.$extension;
        $existing = Media::where('disk', CentralMediaService::DISK)->where('storage_key', $key)->first();
        if ($existing !== null) {
            if ($existing->model_type !== 'saas_payment' || $existing->model_id !== $payment->getKey() || $existing->collection_name !== $collection) {
                $this->fail('Cette pièce privée appartient à une autre opération.');
            }
            $this->assertFile($existing);

            return $existing;
        }
        $temporary = $key.'.'.Str::uuid().'.part';
        try {
            if (! $disk->put($temporary, $bytes, ['visibility' => 'private']) || ! hash_equals(hash('sha256', $bytes), hash('sha256', $disk->get($temporary))) || ! $disk->move($temporary, $key)) {
                $this->fail('Le stockage privé est indisponible. L’opération reste consultable et peut être reprise.');
            }
        } catch (Throwable $exception) {
            if ($exception instanceof ValidationException) {
                throw $exception;
            }
            report($exception);
            $this->fail('Le stockage privé est indisponible. L’opération est conservée ; réessayez après correction.');
        } finally {
            try {
                $disk->delete($temporary);
            } catch (Throwable $cleanup) {
                report($cleanup);
            }
        }
        $media = new Media;
        $media->forceFill(['model_type' => 'saas_payment', 'model_id' => $payment->getKey(), 'created_by_id' => $actor->id,
            'collection_name' => $collection, 'disk' => CentralMediaService::DISK, 'storage_key' => $key,
            'original_name' => $collection.'.'.$extension, 'mime_type' => $mime, 'visibility' => MediaVisibilityEnum::PRIVATE,
            'size_bytes' => strlen($bytes), 'file_hash' => hash('sha256', $bytes), 'position' => 1, 'is_primary' => true])->save();

        return $media;
    }

    private function render(string $html): string
    {
        $pdf = new Dompdf(new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false,
            'defaultFont' => 'DejaVu Sans', 'chroot' => base_path('vendor/dompdf')]));
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }

    private function assertFile(Media $media): void
    {
        $disk = Storage::disk(CentralMediaService::DISK);
        if ($media->disk !== CentralMediaService::DISK || $media->visibility !== MediaVisibilityEnum::PRIVATE
            || ! str_starts_with($media->storage_key, CentralMediaService::DIRECTORY.'/payments/')
            || str_contains($media->storage_key, '..') || ! $disk->exists($media->storage_key)
            || ! hash_equals((string) $media->file_hash, hash('sha256', $disk->get($media->storage_key)))) {
            $this->fail('L’intégrité du justificatif ou du reçu ne peut pas être confirmée.');
        }
    }

    private function authorizeWriter(User $actor, SaasPayment $payment): void
    {
        abort_if(tenancy()->initialized, 403);
        if (! Gate::forUser($actor)->allows('verify', $payment)) {
            Gate::forUser($actor)->authorize('view', $payment);
        }
    }

    private function withProofLock(SaasPayment $payment, Closure $write): void
    {
        abort_if(tenancy()->initialized, 403);
        $lock = 'proof:'.substr(hash('sha256', DB::connection()->getDatabaseName().':'.$payment->getKey()), 0, 50);
        if ((int) DB::selectOne('SELECT GET_LOCK(?,10) AS acquired', [$lock])->acquired !== 1) {
            $this->fail('Le justificatif est déjà en préparation. Réessayez.');
        }
        try {
            $write();
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['proof' => $message]);
    }
}
