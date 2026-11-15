<?php

namespace App\Http\Controllers\Central\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\SaasPayment;
use App\Services\Central\Authorization\CentralUserAccess;
use App\Services\Central\Billing\OwnerPaymentCorrectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Administrative correction inbox and decision endpoint, backed by the existing activity log. */
final class OwnerPaymentCorrectionReviewController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        Gate::forUser($this->centralActor())->authorize('viewAny', SaasPayment::class);

        return to_route('central.invoices.index')->withFragment('corrections');
    }

    public function review(
        Request $request,
        Activity $report,
        CentralUserAccess $access,
        OwnerPaymentCorrectionService $corrections,
    ): RedirectResponse {
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('viewAny', SaasPayment::class);
        abort_unless($access->allows($actor, 'saas.payments.reconcile'), 403);

        $values = $request->validate(OwnerPaymentCorrectionService::reviewRules());
        $decision = (string) $values['decision'];
        $note = (string) $values['note'];
        $creditUuid = isset($values['credit_uuid']) ? (string) $values['credit_uuid'] : null;

        $corrections->review($actor, $report, $decision, $note, $creditUuid);

        $message = $decision === OwnerPaymentCorrectionService::DECISION_CREDIT_NOTE
            ? 'Décision enregistrée en référence à l’avoir émis. Le paiement n’a pas été modifié.'
            : 'Décision enregistrée. Le paiement n’a pas été modifié.';

        $payment = $report->subject;
        abort_unless($payment instanceof SaasPayment, 404);

        return to_route('central.invoices.show', $payment->invoice)->withFragment('payment-'.$payment->getAttribute('uuid'))->with('status', $message);
    }
}
