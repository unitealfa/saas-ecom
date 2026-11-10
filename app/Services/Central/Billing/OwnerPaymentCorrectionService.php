<?php

namespace App\Services\Central\Billing;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\SaasCreditNote;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Append-only correction request. A merchant cannot correct received money,
 * change an invoice or undo a confirmed payment.
 * Administrators review this in the pre-existing payment activity history.
 */
final class OwnerPaymentCorrectionService
{
    public const string EVENT = 'saas_owner_payment_correction_requested';

    public const string REVIEWED_EVENT = 'saas_owner_payment_correction_reviewed';

    public const string DECISION_EXAMINED = 'examined_without_change';

    public const string DECISION_CREDIT_NOTE = 'corrected_with_credit';

    public function __construct(private readonly CentralUserAccess $access) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return ['request_id' => ['required', 'uuid:4'], 'reason' => ['required', 'string', 'min:10', 'max:2000']];
    }

    /** @return array<string, list<mixed>> */
    public static function reviewRules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in([self::DECISION_EXAMINED, self::DECISION_CREDIT_NOTE])],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
            'credit_uuid' => ['nullable', 'uuid:4'],
        ];
    }

    public function report(User $actor, SaasPayment $payment, string $requestId, string $reason): void
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize('viewOwn', SaasPayment::class);
        Gate::forUser($actor)->authorize('view', $payment);
        $reason = trim($reason);
        Validator::make(['request_id' => $requestId, 'reason' => $reason], self::rules())->validate();
        $requestId = strtolower($requestId);

        // Lock the payment to make a repeated request idempotent per payment.
        DB::transaction(function () use ($actor, $payment, $requestId, $reason): void {
            $current = SaasPayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('viewOwn', SaasPayment::class);
            Gate::forUser($actor)->authorize('view', $current);

            $prior = Activity::forSubject($current)
                ->where('event', self::EVENT)
                ->where('properties->request_id', $requestId)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($prior !== null) {
                if ((string) ($prior->properties['reason'] ?? '') !== $reason
                    || (int) $prior->causer_id !== $actor->id
                    || $prior->causer_type !== $actor->getMorphClass()) {
                    throw ValidationException::withMessages([
                        'request_id' => 'Cette clé de demande a déjà été utilisée avec un autre contenu.',
                    ]);
                }

                return; // Retry of the same POST must not create a duplicate.
            }

            activity('central')
                ->causedBy($actor)
                ->performedOn($current)
                ->event(self::EVENT)
                ->withProperties([
                    'request_id' => $requestId,
                    'payment_uuid' => $current->uuid,
                    'reason' => $reason,
                    'reported_status' => $current->getAttribute('transfer_status')->value,
                ])
                ->log('Signalement de correction financière à examiner par un administrateur.');
        }, 3);
    }

    public function review(
        User $admin,
        Activity $report,
        string $decision,
        string $note,
        ?string $creditUuid = null,
    ): void {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($admin)->authorize('viewAny', SaasPayment::class);
        abort_unless($this->access->allows($admin, 'saas.payments.reconcile'), 403);

        $values = Validator::make([
            'decision' => $decision,
            'note' => trim($note),
            'credit_uuid' => $creditUuid,
        ], self::reviewRules())->validate();
        $decision = (string) $values['decision'];
        $note = (string) $values['note'];
        $creditUuid = isset($values['credit_uuid']) ? (string) $values['credit_uuid'] : null;

        DB::transaction(function () use ($admin, $report, $decision, $note, $creditUuid): void {
            $payment = SaasPayment::query()
                ->whereKey($report->subject_id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentReport = Activity::query()
                ->whereKey($report->getKey())
                ->where('event', self::EVENT)
                ->where('subject_type', $payment->getMorphClass())
                ->where('subject_id', $payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $currentReport->causer_id !== (int) $payment->getAttribute('user_id')
                || $currentReport->causer_type !== (new User)->getMorphClass()
                || ($currentReport->properties['payment_uuid'] ?? null) !== $payment->uuid) {
                abort(404);
            }

            $alreadyReviewed = Activity::forSubject($payment)
                ->where('event', self::REVIEWED_EVENT)
                ->where('properties->report_uuid', $currentReport->uuid)
                ->lockForUpdate()
                ->first() !== null;

            if ($alreadyReviewed) {
                throw ValidationException::withMessages([
                    'decision' => 'Ce signalement a déjà été traité.',
                ]);
            }

            $credit = null;
            if ($decision === self::DECISION_CREDIT_NOTE) {
                if ($creditUuid === null) {
                    throw ValidationException::withMessages([
                        'credit_uuid' => 'Un avoir émis est obligatoire.',
                    ]);
                }

                $credit = SaasCreditNote::query()
                    ->where('uuid', $creditUuid)
                    ->where('original_invoice_id', $payment->getAttribute('document_id'))
                    ->where('user_id', $payment->getAttribute('user_id'))
                    ->where('status', DocumentStatusEnum::ISSUED)
                    ->first();

                if ($credit === null) {
                    throw ValidationException::withMessages([
                        'credit_uuid' => 'Avoir émis correspondant introuvable.',
                    ]);
                }
            } elseif ($creditUuid !== null) {
                throw ValidationException::withMessages([
                    'credit_uuid' => 'Aucun avoir ne doit être indiqué pour cette décision.',
                ]);
            }

            activity('central')
                ->causedBy($admin)
                ->performedOn($payment)
                ->event(self::REVIEWED_EVENT)
                ->withProperties([
                    'report_uuid' => $currentReport->uuid,
                    'decision' => $decision,
                    'note' => $note,
                    'credit_uuid' => $credit?->uuid,
                ])
                ->log('Signalement financier examiné par l’administration.');
        }, 3);
    }
}
