<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Enums\Central\Billing\InvoiceListFilterEnum;
use App\Enums\Central\Billing\PaymentStateEnum;
use App\Enums\Central\Billing\TransferStatusEnum;
use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Models\Central\Billing\SaasCreditNote;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Users\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class InvoicePaymentService
{
    public const int REFUND_RECORD_TYPE = 2;

    /**
     * Filtre SQL avant pagination ; une dette annulée par avoir ne devient pas un paiement.
     *
     * @param  Builder<SaasInvoice>  $query
     */
    public function filter(Builder $query, InvoiceListFilterEnum $filter): void
    {
        $credits = DB::table('saas_invoices as invoice_credits')->whereColumn('invoice_credits.original_invoice_id', 'saas_invoices.id')
            ->where('invoice_credits.document_type', DocumentTypeEnum::CREDIT_NOTE->value)->where('invoice_credits.status', DocumentStatusEnum::ISSUED->value);
        if ($filter === InvoiceListFilterEnum::CORRECTED) {
            $query->whereExists($credits->selectRaw('1'));
        } elseif ($filter === InvoiceListFilterEnum::CANCELLED) {
            $query->where('status', DocumentStatusEnum::CANCELLED);
        } elseif ($filter === InvoiceListFilterEnum::PREPARING) {
            $query->whereIn('status', [DocumentStatusEnum::DRAFT, DocumentStatusEnum::PREPARING]);
        } elseif (in_array($filter, [InvoiceListFilterEnum::PAID, InvoiceListFilterEnum::UNPAID], true)) {
            $query->where('status', DocumentStatusEnum::ISSUED);
            $paid = $this->paidInvoices();
            if ($filter === InvoiceListFilterEnum::PAID) {
                $query->whereIn('id', $paid);
            } else {
                $sum = $credits->selectRaw('COALESCE(SUM(invoice_credits.total_amount), 0)');
                $query->whereNotIn('id', $paid)->where('saas_invoices.total_amount', '>', $sum);
            }
        }
    }

    /** @return Builder<SaasInvoice> */
    private function paidInvoices(): Builder
    {
        $credits = DB::table('saas_invoices as invoice_credits')->whereColumn('invoice_credits.original_invoice_id', 'saas_invoices.id')
            ->where('invoice_credits.document_type', DocumentTypeEnum::CREDIT_NOTE->value)->where('invoice_credits.status', DocumentStatusEnum::ISSUED->value);
        $transfers = DB::table('saas_transfers as invoice_transfers')->whereColumn('invoice_transfers.document_id', 'saas_invoices.id');
        $verified = (clone $transfers)->where('transfer_status', TransferStatusEnum::VERIFIED->value);
        $settled = (clone $verified)->where('record_type', SaasPayment::RECORD_TYPE)->whereNull('reversal_of_id')
            ->whereColumn('invoice_transfers.user_id', 'saas_invoices.user_id')->whereColumn('invoice_transfers.currency', 'saas_invoices.currency');
        $foreign = (clone $transfers)->where(fn ($q) => $q->whereColumn('invoice_transfers.user_id', '!=', 'saas_invoices.user_id')
            ->orWhereColumn('invoice_transfers.currency', '!=', 'saas_invoices.currency'));
        $creditSum = (clone $credits)->selectRaw('COALESCE(SUM(invoice_credits.total_amount), 0)');
        $cash = (clone $transfers)->whereIn('transfer_status', [TransferStatusEnum::VERIFIED->value, TransferStatusEnum::REVERSED->value])
            ->whereIn('record_type', [SaasPayment::RECORD_TYPE, self::REFUND_RECORD_TYPE])
            ->selectRaw('COALESCE(SUM(CASE WHEN record_type = ? THEN amount ELSE -amount END), 0)
                + (SELECT COALESCE(SUM(credit.total_amount), 0) FROM saas_invoices AS credit
                   WHERE credit.original_invoice_id = saas_invoices.id AND credit.document_type = ? AND credit.status = ?)',
                [SaasPayment::RECORD_TYPE, DocumentTypeEnum::CREDIT_NOTE->value, DocumentStatusEnum::ISSUED->value]);
        $brokenReversals = (clone $transfers)->where(function ($q): void {
            $q->where(fn ($reversed) => $reversed->where('transfer_status', TransferStatusEnum::REVERSED->value)
                ->whereNotExists(fn ($inverse) => $inverse->selectRaw('1')->from('saas_transfers as inverse')
                    ->whereColumn('inverse.reversal_of_id', 'invoice_transfers.id')->where('inverse.transfer_status', TransferStatusEnum::VERIFIED->value)
                    ->whereColumn('inverse.record_type', 'invoice_transfers.record_type')->whereColumn('inverse.document_id', 'invoice_transfers.document_id')
                    ->whereRaw('inverse.amount = -invoice_transfers.amount')))
                ->orWhere(fn ($inverse) => $inverse->whereNotNull('invoice_transfers.reversal_of_id')
                    ->where('transfer_status', TransferStatusEnum::VERIFIED->value)
                    ->whereNotExists(fn ($original) => $original->selectRaw('1')->from('saas_transfers as original')
                        ->whereColumn('original.id', 'invoice_transfers.reversal_of_id')->where('original.transfer_status', TransferStatusEnum::REVERSED->value)
                        ->whereColumn('original.record_type', 'invoice_transfers.record_type')->whereColumn('original.document_id', 'invoice_transfers.document_id')
                        ->whereRaw('original.amount = -invoice_transfers.amount')));
        });

        return SaasInvoice::select('id')->where('status', DocumentStatusEnum::ISSUED)->where(function (Builder $query) use ($credits, $verified, $settled, $foreign, $creditSum, $cash, $brokenReversals): void {
            $query->where(function (Builder $plain) use ($credits, $verified, $settled): void {
                $count = (clone $verified)->selectRaw('COUNT(*)');
                $plain->whereNotExists((clone $credits)->selectRaw('1'))->where(DB::raw('1'), '=', $count)
                    ->whereExists((clone $settled)->whereColumn('invoice_transfers.amount', 'saas_invoices.total_amount')->whereNotNull('validated_at')->selectRaw('1'));
            })->orWhere(function (Builder $corrected) use ($credits, $settled, $foreign, $creditSum, $cash, $brokenReversals): void {
                $count = (clone $settled)->selectRaw('COUNT(*)');
                $corrected->whereExists((clone $credits)->selectRaw('1'))->whereNotExists((clone $foreign)->selectRaw('1'))
                    ->whereNotExists((clone $brokenReversals)->selectRaw('1'))->where(DB::raw('1'), '=', $count)
                    ->where('saas_invoices.total_amount', '>', $creditSum)
                    ->where('saas_invoices.total_amount', '<=', $cash);
            });
        });
    }

    /** @return array<string, string> */
    public function balance(User $actor, SaasInvoice $invoice): array
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize('viewFinancialBalance', $invoice);
        $credits = BigDecimal::of('0.00');
        foreach (SaasCreditNote::where('original_invoice_id', $invoice->id)->where('status', DocumentStatusEnum::ISSUED)
            ->when(DB::transactionLevel() > 0, fn (Builder $query) => $query->lockForUpdate())->get(['total_amount']) as $credit) {
            $credits = $credits->plus($credit->total_amount);
        }
        $transfers = SaasPayment::withoutGlobalScope('payment')->where('document_id', $invoice->id)->orderBy('id')
            ->when(DB::transactionLevel() > 0, fn (Builder $query) => $query->lockForUpdate())->get();
        if ($this->hasForeignTransfer($invoice, $transfers)) {
            throw ValidationException::withMessages(['balance' => 'Le journal contient une autre devise ou un autre propriétaire. Un rapprochement est nécessaire.']);
        }
        $payments = $this->netTransfers($transfers, SaasPayment::RECORD_TYPE);
        $refunds = $this->netTransfers($transfers, self::REFUND_RECORD_TYPE);
        $reserved = BigDecimal::of('0.00');
        foreach ($transfers as $transfer) {
            if ($transfer->getAttribute('record_type') === self::REFUND_RECORD_TYPE && $transfer->getAttribute('reversal_of_id') === null
                && in_array($transfer->getAttribute('transfer_status'), [TransferStatusEnum::DECLARED, TransferStatusEnum::APPROVED, TransferStatusEnum::UNCERTAIN], true)) {
                $reserved = $reserved->plus($transfer->getAttribute('amount'));
            }
        }
        $due = BigDecimal::of($invoice->status === DocumentStatusEnum::ISSUED ? $invoice->total_amount : '0.00')->minus($credits);
        $funds = $payments->minus($refunds);
        if ($due->isNegative() || $payments->isNegative() || $refunds->isNegative() || $refunds->isGreaterThan($payments)) {
            throw ValidationException::withMessages(['balance' => 'Le journal financier est incohérent. Un rapprochement est nécessaire.']);
        }
        $remaining = $due->minus($funds);
        $surplus = $funds->minus($due);

        return ['invoice_total' => $invoice->total_amount, 'credits_total' => (string) $credits, 'net_due' => (string) $due,
            'payments' => (string) $payments, 'refunds' => (string) $refunds, 'funds_retained' => (string) $funds,
            'remaining' => (string) ($remaining->isPositive() ? $remaining : BigDecimal::of('0.00')),
            'surplus' => (string) ($surplus->isPositive() ? $surplus : BigDecimal::of('0.00')), 'refunds_reserved' => (string) $reserved];
    }

    public function synchronizeInstallment(User $actor, SaasInvoice $invoice): void
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize('synchronizeBalance', $invoice);
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Financial reconciliation requires the locked central transaction.');
        }
        $balance = $this->balance($actor, $invoice);
        $due = $invoice->installment()->lockForUpdate()->firstOrFail();
        if ($due->installment_status === InstallmentStatusEnum::CANCELLED) {
            return;
        }
        $status = BigDecimal::of($balance['remaining'])->isZero() ? InstallmentStatusEnum::PAID
            : (BigDecimal::of($balance['funds_retained'])->isPositive() ? InstallmentStatusEnum::PARTIALLY_PAID : InstallmentStatusEnum::PENDING);
        $due->forceFill(['installment_status' => $status])->save();
    }

    /** @param Collection<int, SaasPayment> $transfers */
    private function netTransfers(Collection $transfers, int $type): BigDecimal
    {
        $sum = BigDecimal::of('0.00');
        foreach ($transfers as $transfer) {
            if ($transfer->getAttribute('record_type') !== $type) {
                continue;
            }
            $status = $transfer->getAttribute('transfer_status');
            if (! in_array($status, [TransferStatusEnum::VERIFIED, TransferStatusEnum::REVERSED], true)) {
                continue;
            }
            $inverseId = $transfer->getAttribute('reversal_of_id');
            if ($status === TransferStatusEnum::REVERSED || $inverseId !== null) {
                $original = $inverseId !== null ? $transfers->firstWhere('id', $inverseId) : $transfer;
                $inverse = $original === null ? null : $transfers->first(fn (SaasPayment $row): bool => $row->getAttribute('reversal_of_id') === $original->getKey()
                    && $row->getAttribute('transfer_status') === TransferStatusEnum::VERIFIED);
                if ($original === null || $inverse === null || $original->getAttribute('transfer_status') !== TransferStatusEnum::REVERSED
                    || $inverse->getAttribute('record_type') !== $type
                    || ! BigDecimal::of($inverse->getAttribute('amount'))->isEqualTo(BigDecimal::of($original->getAttribute('amount'))->negated())) {
                    throw ValidationException::withMessages(['balance' => 'Une contrepassation est incomplète. Aucun solde ne peut être confirmé.']);
                }
            }
            $sum = $sum->plus($transfer->getAttribute('amount'));
        }

        return $sum;
    }

    /** @param Collection<int, SaasPayment> $transfers */
    private function hasForeignTransfer(SaasInvoice $invoice, Collection $transfers): bool
    {
        return $transfers->contains(fn (SaasPayment $transfer): bool => $transfer->getAttribute('user_id') !== $invoice->user_id
            || $transfer->getAttribute('currency') !== $invoice->currency);
    }

    /** @param Collection<int, SaasInvoice> $invoices
     * @return array<int, PaymentStateEnum>
     */
    public function states(User $actor, Collection $invoices): array
    {
        abort_if(tenancy()->initialized, 403);
        foreach ($invoices as $invoice) {
            $this->authorize($actor, $invoice);
        }
        $transfers = SaasPayment::withoutGlobalScope('payment')->whereIn('document_id', $invoices->pluck('id'))->get()->groupBy('document_id');
        $credits = SaasCreditNote::whereIn('original_invoice_id', $invoices->pluck('id'))->where('status', DocumentStatusEnum::ISSUED)->get()->groupBy('original_invoice_id');

        return $invoices->mapWithKeys(fn (SaasInvoice $invoice): array => [
            $invoice->id => $this->resolve($invoice, $transfers->get($invoice->id, collect()), $credits->get($invoice->id, collect()))[0],
        ])->all();
    }

    /** Point de lecture pour un futur reçu, sans créer ni confirmer un paiement.
     */
    public function confirmedPayment(User $actor, SaasInvoice $invoice): ?SaasPayment
    {
        abort_if(tenancy()->initialized, 403);
        $current = SaasInvoice::whereKey($invoice->id)->firstOrFail();
        $this->authorize($actor, $current);
        $transfers = SaasPayment::withoutGlobalScope('payment')->where('document_id', $current->id)->get();

        return $this->resolve($current, $transfers, SaasCreditNote::where('original_invoice_id', $current->id)->where('status', DocumentStatusEnum::ISSUED)->get())[1];
    }

    private function authorize(User $actor, SaasInvoice $invoice): void
    {
        if (! Gate::forUser($actor)->allows('view', $invoice)) {
            Gate::forUser($actor)->authorize('viewOwnInvoice', $invoice);
        }
    }

    /** @param Collection<int, SaasPayment> $transfers
     * @param  Collection<int, SaasCreditNote>  $credits
     * @return array{PaymentStateEnum, ?SaasPayment}
     */
    private function resolve(SaasInvoice $invoice, Collection $transfers, Collection $credits): array
    {
        if ($credits->isNotEmpty()) {
            if ($this->hasForeignTransfer($invoice, $transfers)) {
                return [PaymentStateEnum::ANOMALY, null];
            }
            $due = BigDecimal::of($invoice->total_amount);
            foreach ($credits as $credit) {
                $due = $due->minus($credit->total_amount);
            }
            $cash = $this->netTransfers($transfers, SaasPayment::RECORD_TYPE)->minus($this->netTransfers($transfers, self::REFUND_RECORD_TYPE));
            $settled = $transfers->filter(fn (SaasPayment $payment): bool => $payment->getAttribute('record_type') === SaasPayment::RECORD_TYPE
                && $payment->getAttribute('transfer_status') === TransferStatusEnum::VERIFIED && $payment->getAttribute('reversal_of_id') === null);
            if ($due->isZero()) {
                return [PaymentStateEnum::CREDITED, $settled->count() === 1 ? $settled->first() : null];
            }
            if (! $due->isNegative() && $settled->count() === 1 && $cash->isGreaterThanOrEqualTo($due)) {
                return [PaymentStateEnum::PAID, $settled->first()];
            }

            return [$transfers->isEmpty() ? PaymentStateEnum::DUE : PaymentStateEnum::REVIEW, null];
        }
        $verified = $transfers->filter(fn (SaasPayment $payment): bool => $payment->getAttribute('transfer_status') === TransferStatusEnum::VERIFIED);
        if ($verified->isNotEmpty()) {
            $payment = $verified->first();
            if ($verified->count() === 1 && $invoice->status === DocumentStatusEnum::ISSUED
                && $payment->getAttribute('record_type') === SaasPayment::RECORD_TYPE
                && $payment->getAttribute('reversal_of_id') === null
                && $payment->getAttribute('user_id') === $invoice->user_id
                && $payment->getAttribute('currency') === $invoice->currency
                && $payment->getAttribute('amount') === $invoice->total_amount
                && $payment->getAttribute('validated_at') !== null) {
                return [PaymentStateEnum::PAID, $payment];
            }

            return [PaymentStateEnum::ANOMALY, null];
        }
        if ($transfers->contains(fn (SaasPayment $payment): bool => in_array($payment->getAttribute('transfer_status'),
            [TransferStatusEnum::DECLARED, TransferStatusEnum::APPROVED, TransferStatusEnum::UNCERTAIN], true))) {
            return [PaymentStateEnum::REVIEW, null];
        }

        return [$transfers->isEmpty() ? PaymentStateEnum::DUE : PaymentStateEnum::ANOMALY, null];
    }
}
