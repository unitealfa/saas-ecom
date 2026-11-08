<?php

namespace App\Services\Central\Plans;

use App\Models\Central\Plans\Plan;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PlanCreationService
{
    private const string FREE_CODE = 'free';

    private const array PRICE = ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:999999999999.99'];

    private const array AUDITED = ['code', 'name', 'description', 'monthly_price', 'annual_price', 'version', 'is_active'];

    public function __construct(private readonly AuthorizationMutation $mutations) {}

    /** @return array<string, array<int, mixed>> */
    public static function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255', 'regex:/^[a-z][a-z0-9._-]*$/D'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'monthly_price' => self::PRICE,
            'annual_price' => self::PRICE,
            ...array_fill_keys(['id', 'uuid', 'version', 'is_active', 'deleted_at', 'features'], ['prohibited']),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        foreach (['code', 'name', 'description'] as $field) {
            if (is_string($data[$field] ?? null)) {
                $data[$field] = trim($data[$field]);
            }
        }

        if (is_string($data['code'] ?? null)) {
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

                if ($values['code'] === self::FREE_CODE || Plan::withTrashed()->where('code', $values['code'])->exists()) {
                    $this->codeReserved();
                }

                $plan = new Plan($values);
                $plan->forceFill(['version' => 1, 'is_active' => false]);

                try {
                    $plan->save();
                } catch (UniqueConstraintViolationException) {
                    $this->codeReserved();
                }

                activity()->causedBy($actor)->performedOn($plan)->event('plan_created')
                    ->withProperties(['after' => $plan->only(self::AUDITED)])
                    ->log('Plan commercial créé.');

                return $plan;
            });
    }

    private function codeReserved(): never
    {
        throw ValidationException::withMessages(['code' => 'Ce code est déjà réservé.']);
    }
}