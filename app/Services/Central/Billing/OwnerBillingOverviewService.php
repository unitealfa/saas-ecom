<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\TransferStatusEnum;
use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\SaasCreditNote;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\Central\Users\User;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only central billing overview for a merchant.
 * Authoritative amounts are provided by the existing invoice/payment services.
 * No payment state, invoice or subscription is mutated here.
 */
final readonly class OwnerBillingOverviewService
{
    public function __construct(private InvoicePaymentService $balances) {}

    /** @return array<string, mixed> */
    public function show(User $actor): array
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize('viewOwn', SaasInvoice::class);
        Gate::forUser($actor)->authorize('viewOwn', SaasPayment::class);
        Gate::forUser($actor)->authorize('viewOwn', SaasCreditNote::class);

        // Every query is explicitly scoped to the authenticated owner, irrespective
        // of client-supplied query strings or values in other records.
        $dues = SubscriptionInstallment::query()
            ->where('user_id', $actor->id)
            ->with('invoices')
            ->orderByDesc('period_starts_at')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'dues_page')
            ->withQueryString();

        $invoices = SaasInvoice::query()
            ->where('user_id', $actor->id)
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'invoices_page')
            ->withQueryString();

        $payments = SaasPayment::query()
            ->where('user_id', $actor->id)
            ->with('invoice')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'payments_page')
            ->withQueryString();

        $credits = SaasCreditNote::query()
            ->where('user_id', $actor->id)
            ->where('status', DocumentStatusEnum::ISSUED)
            ->with('originalInvoice')
            ->orderByDesc('id')
            ->paginate(10, ['*'], 'credits_page')
            ->withQueryString();

        $reports = Activity::query()
            ->where('event', OwnerPaymentCorrectionService::EVENT)
            ->where('causer_type', $actor->getMorphClass())
            ->where('causer_id', $actor->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return [
            'dues' => $dues,
            'invoices' => $invoices,
            'invoiceStates' => $this->balances->states($actor, $invoices->getCollection()),
            'payments' => $payments,
            'credits' => $credits,
            'reports' => $reports,
            'pendingDueCount' => SubscriptionInstallment::query()
                ->where('user_id', $actor->id)
                ->whereIn('installment_status', [
                    InstallmentStatusEnum::PENDING->value,
                    InstallmentStatusEnum::PARTIALLY_PAID->value, // Legacy anomaly, never an accepted part-payment.
                ])->count(),
            'verifiedPaymentCount' => SaasPayment::query()
                ->where('user_id', $actor->id)
                ->where('transfer_status', TransferStatusEnum::VERIFIED)
                ->whereNull('reversal_of_id')
                ->count(),
        ];
    }
}
