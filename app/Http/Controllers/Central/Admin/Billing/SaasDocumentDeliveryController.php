<?php

namespace App\Http\Controllers\Central\Admin\Billing;

use App\Enums\Central\Billing\DeliveryStatusEnum;
use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Billing\RetrySaasDocumentRequest;
use App\Http\Requests\Central\Billing\SendSaasDocumentRequest;
use App\Models\Central\Billing\SaasCreditNote;
use App\Models\Central\Billing\SaasDocumentDelivery;
use App\Models\Central\Billing\SaasInvoice;
use App\Services\Central\Billing\SaasDocumentDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SaasDocumentDeliveryController extends Controller
{
    public function __construct(private readonly SaasDocumentDeliveryService $deliveries) {}

    public function send(SendSaasDocumentRequest $request): RedirectResponse
    {
        $delivery = $this->deliveries->request($this->centralActor(), $request->validated('document_uuid'));

        $message = match ($delivery->delivery_status) {
            DeliveryStatusEnum::SENT, DeliveryStatusEnum::DELIVERED => 'Cet envoi était déjà enregistré comme effectué. Aucun doublon créé.',
            DeliveryStatusEnum::SENDING => 'Un envoi est déjà en cours. Aucune deuxième tentative déclenchée.',
            default => 'Demande enregistrée. Le worker traite l’envoi ; consultez son état avant de conclure.',
        };

        return $this->redirect($delivery, $message);
    }

    public function retry(RetrySaasDocumentRequest $request, SaasDocumentDelivery $delivery): RedirectResponse
    {
        $delivery = $this->deliveries->retry($this->centralActor(), $delivery, $request->validated('expected_state'));

        return $this->redirect($delivery, 'Relance enregistrée. Elle ne prouve pas encore l’envoi.');
    }

    public function resend(RetrySaasDocumentRequest $request, SaasDocumentDelivery $delivery): RedirectResponse
    {
        $delivery = $this->deliveries->resend($this->centralActor(), $delivery, $request->validated('expected_state'));

        return $this->redirect($delivery, 'Renvoi demandé. Les tentatives précédentes sont conservées ; consultez le résultat du worker.');
    }

    private function redirect(SaasDocumentDelivery $delivery, string $message): RedirectResponse
    {
        return $this->documentRedirect($delivery)->with('status', $message);
    }

    private function documentRedirect(SaasDocumentDelivery $delivery): RedirectResponse
    {
        $credit = $delivery->document_type === DocumentTypeEnum::CREDIT_NOTE;
        $document = $credit ? SaasCreditNote::findOrFail($delivery->document_id) : SaasInvoice::findOrFail($delivery->document_id);

        return Gate::forUser($this->centralActor())->allows('view', $document)
            ? to_route($credit ? 'central.credit-notes.show' : 'central.invoices.show', $document) : to_route('dashboard');
    }
}
