<?php

namespace App\Http\Controllers\Central\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\SaasPayment;
use App\Services\Central\Billing\OwnerPaymentCorrectionService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/** Read-only administrative inbox, backed by the already-existing activity log. */
final class OwnerPaymentCorrectionReviewController extends Controller
{
    public function __invoke(): View
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($this->centralActor())->authorize('viewAny', SaasPayment::class);

        $reports = Activity::query()
            ->where('event', OwnerPaymentCorrectionService::EVENT)
            ->where('subject_type', (new SaasPayment)->getMorphClass())
            ->with('subject')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('central.pages.admin.billing.payments.corrections', compact('reports'));
    }
}
