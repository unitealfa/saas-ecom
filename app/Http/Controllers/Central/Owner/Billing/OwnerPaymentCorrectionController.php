<?php

namespace App\Http\Controllers\Central\Owner\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Billing\ReportOwnPaymentCorrectionRequest;
use App\Models\Central\Billing\SaasPayment;
use App\Services\Central\Billing\OwnerPaymentCorrectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class OwnerPaymentCorrectionController extends Controller
{
    public function create(SaasPayment $payment): RedirectResponse
    {
        Gate::forUser($this->centralActor())->authorize('viewOwn', SaasPayment::class);
        Gate::forUser($this->centralActor())->authorize('view', $payment);

        return to_route('owner.invoices.show', $payment->invoice)->withFragment('payment-'.$payment->uuid);
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

        return to_route('owner.invoices.show', $payment->invoice)->withFragment('payment-'.$payment->uuid)->with(
            'status',
            'Signalement conservé dans l’historique. L’administration peut examiner la demande ; seule l’émission d’un avoir corrige la facture. Aucun paiement n’a été modifié.',
        );
    }
}
