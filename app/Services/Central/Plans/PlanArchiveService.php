<?php

namespace App\Services\Central\Plans;

use App\Models\Central\Plans\Plan;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

class PlanArchiveService
{
    public function __construct(private readonly AuthorizationMutation $mutations) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'expected_state' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            ...array_fill_keys(['id', 'uuid', 'code', 'version', 'name', 'description', 'monthly_price', 'annual_price', 'is_active', 'deleted_at', 'features'], ['prohibited']),
        ];
    }

    /** @param  array<string, mixed>  $data */
    public function archive(User $actor, Plan $plan, array $data): void
    {
        $this->mutations->run($actor, 'archive',
            fn (): Plan => Plan::whereKey($plan->id)->lockForUpdate()->firstOrFail(),
            function (Plan $plan) use ($actor, $data): void {
                $values = Validator::make($data, self::rules())->validate();
                $features = $plan->features()->orderBy('feature_id')->lockForUpdate()->get();
                if (! hash_equals($plan->compositionState($features), $values['expected_state'])) {
                    throw ValidationException::withMessages(['expected_state' => 'Le plan a changé. Rechargez avant de recommencer.']);
                }
                if (DB::table('subscriptions')->where('plan_id', $plan->id)->lockForUpdate()->first(['id']) !== null) {
                    throw ValidationException::withMessages(['plan' => 'Cette version est liée à un abonnement et ne peut pas être archivée.']);
                }

                $before = $plan->only(['uuid', 'code', 'version', 'is_active', 'deleted_at']);
                if ($plan->is_active) {
                    $plan->forceFill(['is_active' => false])->save();
                }
                if ($plan->delete() !== true) {
                    throw new LogicException('La version du plan n’a pas pu être archivée.');
                }
                activity()->causedBy($actor)->performedOn($plan)->event('plan_archived')
                    ->withProperties(['before' => $before, 'after' => $plan->only(['uuid', 'code', 'version', 'is_active', 'deleted_at'])])
                    ->log('Version du plan archivée.');
            });
    }
}
