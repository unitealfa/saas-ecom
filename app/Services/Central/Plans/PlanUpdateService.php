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

    private const array FORBIDDEN_FIELDS = ['id', 'uuid', 'code', 'version', 'is_active', 'deleted_at', 'features'];

    public function __construct(private readonly AuthorizationMutation $mutations) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'expected_state' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            ...Arr::only(PlanCreationService::rules(), self::FIELDS),
            ...array_fill_keys(self::FORBIDDEN_FIELDS, ['prohibited']),
        ];
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, Plan $plan, array $data): void
    {
        $this->mutations->run($actor, 'update',
            fn (): Plan => Plan::whereKey($plan->id)->lockForUpdate()->firstOrFail(),
            function (Plan $plan) use ($actor, $data): void {
                $values = Validator::make(PlanCreationService::normalize($data), self::rules())->validate();

                $features = $plan->features()->orderBy('feature_id')->lockForUpdate()->get();

                if (! hash_equals($plan->compositionState($features), $values['expected_state'])) {
                    $this->fail('expected_state', 'Le plan a changé. Rechargez avant de recommencer.');
                }

                // Une version déjà souscrite est figée : toute modification passe par une nouvelle version.
                if (DB::table('subscriptions')->where('plan_id', $plan->id)->lockForUpdate()->value('id') !== null) {
                    $this->fail('plan', 'Cette version est liée à un abonnement et ne peut plus être modifiée.');
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

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}