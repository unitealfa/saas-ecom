<?php

namespace App\Services\Central\Plans;

use App\Models\Central\Plans\Plan;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PlanUpdateService
{
    private const array FIELDS = ['name', 'description', 'monthly_price', 'annual_price'];

    public function __construct(private readonly AuthorizationMutation $mutations) {}

    public static function rules(): array
    {
        return [
            'expected_state' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            ...Arr::only(PlanCreationService::rules(), self::FIELDS),
            ...array_fill_keys(['id', 'uuid', 'code', 'version', 'is_active', 'deleted_at', 'features'], ['prohibited']),
        ];
    }

    public function update(User $actor, Plan $plan, array $data): void
    {
        $this->mutations->run($actor, 'update',
            fn (): Plan => Plan::whereKey($plan->id)->lockForUpdate()->firstOrFail(),
            function (Plan $plan) use ($actor, $data): void {
                $values = Validator::make(PlanCreationService::normalize($data), self::rules())->validate();
                $features = $plan->features()->orderBy('feature_id')->lockForUpdate()->get();
                if (! hash_equals($plan->compositionState($features), $values['expected_state'])) {
                    throw ValidationException::withMessages(['expected_state' => 'Le plan a changé. Rechargez avant de recommencer.']);
                }
                if (DB::table('subscriptions')->where('plan_id', $plan->id)->lockForUpdate()->first(['id']) !== null) {
                    throw ValidationException::withMessages(['plan' => 'Cette version est liée à un abonnement et ne peut plus être modifiée.']);
                }
                $before = $plan->only(self::FIELDS);
                $plan->fill(Arr::only($values, self::FIELDS));
                if (! $plan->isDirty()) {
                    return;
                }
                $plan->save();
                activity()->causedBy($actor)->performedOn($plan)->event('plan_updated')
                    ->withProperties(['before' => $before, 'after' => $plan->only(self::FIELDS)])
                    ->log('Informations du plan modifiées.');
            });
    }
}
