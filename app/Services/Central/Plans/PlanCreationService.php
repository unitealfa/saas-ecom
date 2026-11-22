<?php

namespace App\Services\Central\Plans;

use App\Enums\Central\Features\ValueTypeEnum;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
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
            'features' => ['required', 'array', 'min:1', 'list'],
            'features.*.uuid' => ['required', 'uuid:4', 'distinct'],
            'features.*.limit' => ['nullable', 'integer', 'min:0'],
            'features.*.unlimited' => ['required', 'boolean'],
            ...array_fill_keys(['id', 'uuid', 'version', 'is_active', 'deleted_at'], ['prohibited']),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        if (isset($data['features']) && is_array($data['features'])) {
            $data['features'] = array_values($data['features']);
        }
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

                $features = Feature::whereIn('uuid', array_column($values['features'], 'uuid'))->active()
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('uuid');
                if ($features->count() !== count($values['features'])) {
                    throw ValidationException::withMessages(['features' => 'Une fonctionnalité sélectionnée est indisponible.']);
                }
                foreach ($values['features'] as $item) {
                    $feature = $features->get($item['uuid']);
                    $limit = $item['limit'] ?? null;
                    if ($feature->value_type === ValueTypeEnum::QUANTITY
                        && ((bool) $item['unlimited'] === ($limit !== null))) {
                        throw ValidationException::withMessages(['features' => 'Choisissez une limite ou sélectionnez explicitement « Sans limite ».']);
                    }
                }
                $plan = new Plan(collect($values)->except('features')->all());
                $plan->forceFill(['version' => 1, 'is_active' => false]);

                try {
                    $plan->save();
                } catch (UniqueConstraintViolationException) {
                    $this->codeReserved();
                }

                foreach ($values['features'] as $item) {
                    $feature = $features->get($item['uuid']);
                    PlanFeature::create(['plan_id' => $plan->id, 'feature_id' => $feature->id,
                        'is_active' => true, 'limit' => $feature->value_type === ValueTypeEnum::QUANTITY ? ($item['limit'] ?? null) : null]);
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
