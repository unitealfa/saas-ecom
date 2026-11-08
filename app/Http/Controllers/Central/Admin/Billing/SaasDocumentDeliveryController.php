<?php

namespace App\Http\Controllers\Central\Admin\Billing;

use App\Enums\Central\Billing\DeliveryStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Billing\RetrySaasDocumentRequest;
use App\Http\Requests\Central\Billing\SearchSaasDeliveriesRequest;
use App\Http\Requests\Central\Billing\SendSaasDocumentRequest;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\SaasDocumentDelivery;
use App\Services\Central\Billing\SaasDocumentDeliveryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class SaasDocumentDeliveryController extends Controller
{
    public function __construct(private readonly SaasDocumentDeliveryService $deliveries) {}

    public function index(SearchSaasDeliveriesRequest $request): View
    {
        return view('central.pages.admin.billing.deliveries.index', [
            'deliveries' => $this->deliveries->search($this->centralActor(), $request->validated()),
        ]);
    }

    public function show(SaasDocumentDelivery $delivery): View
    {
        Gate::forUser($this->centralActor())->authorize('view', $delivery);

        return view('central.pages.admin.billing.deliveries.show', [
            'delivery' => $delivery->load(['document', 'owner', 'creator']),
            'history' => Activity::forSubject($delivery)->latest('id')->paginate(20),
        ]);
    }

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

    private function redirect(SaasDocumentDelivery $delivery, string $message): RedirectResponse
    {
        return (Gate::forUser($this->centralActor())->allows('view', $delivery)
            ? to_route('central.deliveries.show', $delivery)
            : to_route('dashboard'))->with('status', $message);
    }
}
