<?php

namespace App\Http\Controllers\Central\Owner\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Billing\ReportOwnPaymentCorrectionRequest;
use App\Models\Central\Billing\SaasPayment;
use App\Services\Central\Billing\OwnerPaymentCorrectionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class OwnerPaymentCorrectionController extends Controller
{
    public function create(SaasPayment $payment): View
    {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('viewOwn', SaasPayment::class);
        Gate::forUser($actor)->authorize('view', $payment);

        return view('central.pages.owner.billing.corrections.create', [
            'payment' => $payment->load('invoice'),
        ]);
    }

    public function store(
        ReportOwnPaymentCorrectionRequest $request,
        SaasPayment $payment,
        OwnerPaymentCorrectionService $corrections,
    ): RedirectResponse {
        $corrections->report(
            $this->centralActor(),
            $payment,
            $request->validated('request_id'),
            trim($request->validated('reason')),
        );

        return to_route('owner.billing.index')->with(
            'status',
            'Signalement conservé dans l’historique. L’administration peut examiner la demande ; seule l’émission d’un avoir corrige la facture. Aucun paiement n’a été modifié.',
        );
    }
}
