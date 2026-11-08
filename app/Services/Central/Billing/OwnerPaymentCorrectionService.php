<?php

namespace App\Services\Central\Billing;

use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Users\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Append-only correction request. A merchant cannot correct received money,
 * change an invoice or undo a confirmed payment.
 * Administrators review this in the pre-existing payment activity history.
 */
final class OwnerPaymentCorrectionService
{
    public const string EVENT = 'saas_owner_payment_correction_requested';

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return ['request_id' => ['required', 'uuid:4'], 'reason' => ['required', 'string', 'min:10', 'max:2000']];
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
}
