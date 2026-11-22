<?php

namespace App\Services\Central\Plans;

use App\Models\Central\Plans\Plan;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Support\Arr;
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
            fn () => $this->fail('plan', 'Créez une nouvelle version pour modifier les informations de cette offre.'));
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
