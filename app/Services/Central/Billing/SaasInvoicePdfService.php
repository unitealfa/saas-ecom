<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Models\Central\Billing\SaasInvoice;
use App\Services\Central\Media\CentralMediaService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaasInvoicePdfService
{
    public const string COLLECTION = 'saas_invoice_pdf';

    private const string SEAL_MARKER = "\n%AYDRA-SEAL:";

    public function storageKey(SaasInvoice $invoice): string
    {
        return CentralMediaService::DIRECTORY.'/invoices/'.$invoice->uuid.'/document.pdf';
    }

    /** @return array{storage_key: string, file_hash: string, size_bytes: int} */
    public function generate(SaasInvoice $invoice): array
    {
        $invoice->load('lines');
        $disk = Storage::disk(CentralMediaService::DISK);
        $key = $this->storageKey($invoice);
        if (! $disk->exists($key)) {
            $options = new Options(['isRemoteEnabled' => false, 'isPhpEnabled' => false,
                'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans', 'chroot' => base_path('vendor/dompdf')]);
            $pdf = new Dompdf($options);
            $pdf->loadHtml(view($invoice->document_type === DocumentTypeEnum::CREDIT_NOTE ? 'central.documents.saas-credit-note' : 'central.documents.saas-invoice', ['invoice' => $invoice])->render(), 'UTF-8');
            $pdf->setPaper('A4');
            $pdf->render();
            $bytes = $pdf->output();
            $bytes .= self::SEAL_MARKER.$this->seal($invoice, $bytes)."\n";
            $temporary = $key.'.'.Str::uuid().'.part';
            try {
                if (! $disk->put($temporary, $bytes, ['visibility' => 'private'])
                    || ! hash_equals(hash('sha256', $bytes), hash('sha256', $disk->get($temporary)))
                    || ! $disk->move($temporary, $key)) {
                    $this->unavailable();
                }
            } finally {
                if ($disk->exists($temporary)) {
                    $disk->delete($temporary);
                }
            }
        }
        $bytes = $disk->get($key);
        if (! str_starts_with($bytes, '%PDF-') || ! str_contains(substr($bytes, -1024), '%%EOF')) {
            $this->unavailable();
        }
        $marker = strrpos($bytes, self::SEAL_MARKER);
        if ($marker === false || ! hash_equals(
            $this->seal($invoice, substr($bytes, 0, $marker)),
            trim(substr($bytes, $marker + strlen(self::SEAL_MARKER))),
        )) {
            $this->unavailable();
        }

        return ['storage_key' => $key, 'file_hash' => hash('sha256', $bytes), 'size_bytes' => strlen($bytes)];
    }

    private function seal(SaasInvoice $invoice, string $bytes): string
    {
        $content = $invoice->only(['uuid', 'number', 'billing_rule_snapshot', 'saas_identity_snapshot', 'customer_identity_snapshot',
            'currency', 'period_starts_at', 'period_ends_at', 'due_at', 'net_amount', 'tax_amount', 'taxes', 'total_amount']);
        $content['lines'] = $invoice->lines
            ->map(fn ($line): array => $line->only(['description', 'quantity', 'net_unit_price', 'net_discount', 'net_amount', 'taxes', 'tax_amount', 'total_amount']))->all();

        if ($invoice->document_type === DocumentTypeEnum::CREDIT_NOTE) {
            $content['correction'] = $invoice->only(['document_type', 'original_invoice_id', 'reason']);
            $content['origins'] = $invoice->lines->map(fn ($line): array => $line->only(['original_invoice_id', 'original_invoice_line_id', 'reason']))->all();
        }

        return hash_hmac('sha256', json_encode($content, JSON_THROW_ON_ERROR).$bytes, config('app.key'));
    }

    private function unavailable(): never
    {
        throw ValidationException::withMessages(['invoice' => 'Le PDF privé ne peut pas être généré ou vérifié. Le numéro reste réservé ; réessayez après correction du stockage.']);
    }
}
