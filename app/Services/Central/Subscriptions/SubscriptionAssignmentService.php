<?php

namespace App\Services\Central\Subscriptions;

use App\Enums\Central\Subscriptions\PeriodEnum;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Users\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubscriptionAssignmentService
{
    public function __construct(private readonly SubscriptionPurchaseService $purchases) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'owner_uuid' => ['required', 'string', 'uuid:4'],
            'plan_uuid' => ['required', 'string', 'uuid:4'],
            'operation_key' => ['required', 'string', 'uuid:4'],
            'period' => ['required', 'integer', Rule::enum(PeriodEnum::class)],
            'expected_amount' => ['required', 'string', 'regex:/^\d{1,6}(?:\.\d{1,2})?$/D'],
            ...array_fill_keys(['id', 'uuid', 'status', 'agreed_amount', 'period_starts_at', 'period_ends_at', 'auto_renew', 'assigned_by_id', 'tenant_id'], ['prohibited']),
        ];
    }

    /** @param array<string, mixed> $data */
    public function assign(User $actor, array $data): Subscription
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize('assign', Subscription::class);
        $values = Validator::make($data, self::rules())->validate();
        $owner = User::where('uuid', strtolower($values['owner_uuid']))->first();
        if ($owner === null) {
            throw ValidationException::withMessages(['owner_uuid' => 'Choisissez un commerçant actif.']);
        }

        return $this->purchases->chooseFor($actor, $owner, [
            'plan_uuid' => $values['plan_uuid'], 'period' => $values['period'],
            'operation_id' => $values['operation_key'],
            'expected_amount' => (string) BigDecimal::of($values['expected_amount'])->toScale(2),
        ]);
    }
}
