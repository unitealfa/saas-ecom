<?php

namespace App\Services\Central\Plans;

use App\Models\Central\Plans\Plan;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PlanCreationService
{
    private const array FIELDS = ['code', 'name', 'description', 'monthly_price', 'annual_price'];

    public function __construct(private readonly AuthorizationMutation $mutations) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255', 'regex:/^[a-z][a-z0-9._-]*$/D'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['bail', 'nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (strlen($value) > 65535) {
                    $fail('La description dépasse la taille autorisée.');
                }
            }],
            'monthly_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'annual_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            ...array_fill_keys(['id', 'uuid', 'version', 'is_active', 'deleted_at', 'features'], ['prohibited']),
        ];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        foreach (['code', 'name', 'description'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }

        if (isset($data['code']) && is_string($data['code'])) {
            $data['code'] = strtolower($data['code']);
        }
        if (($data['description'] ?? null) === '') {
            $data['description'] = null;
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): Plan
    {
        return $this->mutations->run($actor, 'create', fn () => Plan::class,
            function () use ($actor, $data): Plan {
                $values = Validator::make(self::normalize($data), self::rules())->validate();
                $code = $values['code'];
                if ($code === 'free' || Plan::withTrashed()->where('code', $code)->exists()) {
                    throw ValidationException::withMessages(['code' => 'Ce code est déjà réservé.']);
                }

                $plan = new Plan(Arr::only($values, self::FIELDS));
                $plan->forceFill(['version' => 1, 'is_active' => false]);
                try {
                    $plan->save();
                } catch (UniqueConstraintViolationException $exception) {
                    if (str_contains($exception->getMessage(), 'uq_plans_f5b433d863')) {
                        throw ValidationException::withMessages(['code' => 'Ce code est déjà réservé.']);
                    }
                    throw $exception;
                }

                activity()->causedBy($actor)->performedOn($plan)->event('plan_created')
                    ->withProperties(['after' => $plan->only([...self::FIELDS, 'version', 'is_active'])])
                    ->log('Plan commercial créé.');

                return $plan;
            });
    }
}
