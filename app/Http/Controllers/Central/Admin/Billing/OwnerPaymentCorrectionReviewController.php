<?php

namespace App\Http\Controllers\Central\Admin\Billing;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\SaasCreditNote;
use App\Models\Central\Billing\SaasPayment;
use App\Services\Central\Authorization\CentralUserAccess;
use App\Services\Central\Billing\OwnerPaymentCorrectionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Administrative correction inbox and decision endpoint, backed by the existing activity log. */
final class OwnerPaymentCorrectionReviewController extends Controller
{
    public function __invoke(CentralUserAccess $access): View
    {
        abort_if(tenancy()->initialized, 403);
        $actor = $this->centralActor();
        Gate::forUser($actor)->authorize('viewAny', SaasPayment::class);

        $reports = Activity::query()
            ->where('event', OwnerPaymentCorrectionService::EVENT)
            ->where('subject_type', (new SaasPayment)->getMorphClass())
            ->with('subject.invoice')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $reportUuids = $reports->getCollection()->pluck('uuid');
        $reportDecisions = Activity::query()
            ->where('event', OwnerPaymentCorrectionService::REVIEWED_EVENT)
            ->where('subject_type', (new SaasPayment)->getMorphClass())
            ->whereIn('properties->report_uuid', $reportUuids)
            ->orderByDesc('id')
            ->get()
            ->unique(fn (Activity $decision): string => (string) ($decision->properties['report_uuid'] ?? ''))
            ->keyBy(fn (Activity $decision): string => (string) $decision->properties['report_uuid']);

        $invoiceIds = $reports->getCollection()
            ->map(fn (Activity $report): ?int => $report->subject instanceof SaasPayment
                ? (int) $report->subject->getAttribute('document_id')
                : null)
            ->filter(fn (?int $invoiceId): bool => $invoiceId !== null && $invoiceId > 0)
            ->unique();
        $creditsByInvoiceId = SaasCreditNote::query()
            ->whereIn('original_invoice_id', $invoiceIds)
            ->where('status', DocumentStatusEnum::ISSUED)
            ->orderByDesc('issued_at')
            ->get()
            ->groupBy('original_invoice_id');

        return view('central.pages.admin.billing.payments.corrections', [
            'reports' => $reports,
            'reportDecisions' => $reportDecisions,
            'creditsByInvoiceId' => $creditsByInvoiceId,
            'canReview' => $access->allows($actor, 'saas.payments.reconcile'),
        ]);
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

        return to_route('central.payments.corrections.index')->with('status', $message);
    }
}
