<?php

namespace App\Services\Central\Subscriptions;

use App\Enums\Central\Subscriptions\PaymentChoiceEnum;
use App\Enums\Central\Subscriptions\PeriodEnum;
use App\Enums\Central\Subscriptions\RequestStatusEnum;
use App\Enums\Central\Subscriptions\TransitionModeEnum;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Users\User;
use Brick\Math\BigDecimal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriptionAssignmentService
{
    public function __construct(private readonly SubscriptionRequestService $requests) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'owner_uuid' => ['required', 'string', 'uuid:4'],
            'plan_uuid' => ['required', 'string', 'uuid:4'],
            'operation_key' => ['required', 'string', 'uuid:4'],
            'period' => ['required', 'integer', Rule::enum(PeriodEnum::class)],
            'expected_amount' => ['required', 'string', 'regex:/^\d{1,6}(?:\.\d{1,2})?$/D'],
            'payment_choice' => ['sometimes', 'integer', Rule::enum(PaymentChoiceEnum::class)],
            'transition_mode' => ['sometimes', 'integer', Rule::enum(TransitionModeEnum::class)],
            'source_uuid' => ['sometimes', 'uuid:4'],
            'expected_source_state' => ['required_with:source_uuid', 'string', 'size:64'],
            ...SubscriptionRequestService::initialPaymentRules(),
            'received' => ['required', 'accepted'],
            'account' => ['required_if:payment_choice,2', 'nullable', 'string', 'max:255'],
            'reference' => ['required', 'string', 'max:255'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            ...array_fill_keys(['id', 'uuid', 'status', 'agreed_amount', 'period_starts_at', 'period_ends_at', 'auto_renew', 'assigned_by_id', 'tenant_id'], ['prohibited']),
        ];
    }

    /** @param array<string, mixed> $data */
    public function assign(User $actor, array $data, ?UploadedFile $proof = null): Subscription
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize('saas.payments.validate');
        $values = Validator::make([...$data, 'proof' => $proof], self::rules())->validate();
        $owner = User::where('uuid', strtolower($values['owner_uuid']))->first();
        if ($owner === null) {
            throw ValidationException::withMessages(['owner_uuid' => 'Choisissez un commerçant actif.']);
        }
        $source = isset($values['source_uuid']) ? Subscription::where('user_id', $owner->id)->where('uuid', $values['source_uuid'])->firstOrFail() : null;
        Gate::forUser($actor)->authorize('purchaseFor', [Subscription::class, $owner, $source]);

        $request = $this->requests->createFor($actor, $owner, [
            'plan_uuid' => $values['plan_uuid'], 'period' => $values['period'],
            'operation_id' => $values['operation_key'],
            'expected_amount' => (string) BigDecimal::of($values['expected_amount'])->toScale(2),
            'payment_choice' => $values['payment_choice'] ?? PaymentChoiceEnum::CASH->value,
            'transition_mode' => $values['transition_mode'] ?? TransitionModeEnum::DEFERRED->value,
            ...array_intersect_key($values, array_flip(['payment', 'source_uuid', 'expected_source_state'])),
            'confirmation' => array_intersect_key($values, array_flip(['received', 'account', 'reference', 'occurred_at'])),
        ], $proof);
        if (! $request->status->isFinal()) {
            $request = $this->requests->accept($actor, $request, $request->managementState(), [
                'amount' => $request->subscription->agreed_amount, 'operation_id' => $values['operation_key'],
                'received' => $values['received'], 'occurred_at' => $values['occurred_at'],
                'account' => $values['account'] ?? '',
                'reference' => $values['reference'], 'expected_payment_state' => $request->payment?->managementState(),
            ]);
        }
        if ($request->status !== RequestStatusEnum::ACCEPTED) {
            throw ValidationException::withMessages(['subscription' => 'L’attribution n’est pas finalisée. Consultez sa demande : aucune anomalie ni somme signalée ne sera effacée.']);
        }

        return $request->subscription;
    }
}
