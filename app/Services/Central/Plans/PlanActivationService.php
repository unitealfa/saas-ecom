<?php

namespace App\Services\Central\Plans;

use App\Models\Central\Plans\Plan;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PlanActivationService
{
    private const array FORBIDDEN_FIELDS = [
        'id', 'uuid', 'code', 'version', 'name', 'description', 'monthly_price', 'annual_price',
        'deleted_at', 'features',
    ];

    public function __construct(private readonly AuthorizationMutation $mutations) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'expected_state' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'is_active' => ['required', 'boolean'],
            ...array_fill_keys(self::FORBIDDEN_FIELDS, ['prohibited']),
        ];
    }

    /** @param array<string, mixed> $data */
    public function setActive(User $actor, Plan $plan, array $data): void
    {
        $this->mutations->run($actor, 'activate',
            fn (): Plan => Plan::whereKey($plan->id)->lockForUpdate()->firstOrFail(),
            function (Plan $plan) use ($actor, $data): void {
                $values = Validator::make($data, self::rules())->validate();
                $active = (bool) $values['is_active'];
                $current = $plan->features()->orderBy('feature_id')->lockForUpdate()->get();

                if (! hash_equals($plan->compositionState($current), $values['expected_state'])) {
                    throw ValidationException::withMessages(['expected_state' => 'Le plan a changé. Rechargez avant de recommencer.']);
                }

                // Une seule version d'un même code est commercialisée : activer celle-ci désactive les autres.
                if ($active) {
                    $others = Plan::where('code', $plan->code)->orderBy('version')->lockForUpdate()->get()
                        ->filter(fn (Plan $other): bool => $other->id !== $plan->id && $other->is_active);

                    foreach ($others as $other) {
                        Gate::forUser($actor)->authorize('activate', $other);
                        $this->change($actor, $other, false, $plan->uuid);
                    }
                }

                $this->change($actor, $plan, $active, null);
            });
    }

    private function change(User $actor, Plan $plan, bool $active, ?string $replacementUuid): void
    {
        if ($plan->is_active === $active) {
            return;
        }

        $plan->forceFill(['is_active' => $active])->save();

        activity()->causedBy($actor)->performedOn($plan)->event('plan_activation_updated')
            ->withProperties(['before' => ! $active, 'after' => $active, 'replacement_uuid' => $replacementUuid])
            ->log('Commercialisation du plan modifiée.');
    }
}