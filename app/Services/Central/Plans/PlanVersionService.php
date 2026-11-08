<?php

namespace App\Services\Central\Plans;

use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PlanVersionService
{
    private const int MAX_VERSION = 2147483647;

    private const array COPIED_FIELDS = ['code', 'name', 'description', 'monthly_price', 'annual_price'];

    private const array FORBIDDEN_FIELDS = [
        'id', 'uuid', 'code', 'version', 'name', 'description', 'monthly_price', 'annual_price',
        'is_active', 'deleted_at', 'features',
    ];

    public function __construct(private readonly AuthorizationMutation $mutations) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'expected_state' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'expected_latest_version' => ['required', 'integer', 'min:1', 'max:'.self::MAX_VERSION],
            ...array_fill_keys(self::FORBIDDEN_FIELDS, ['prohibited']),
        ];
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, Plan $source, array $data): Plan
    {
        return $this->mutations->run($actor, 'createVersion',
            fn (): Plan => Plan::whereKey($source->id)->lockForUpdate()->firstOrFail(),
            function (Plan $source) use ($actor, $data): Plan {
                $values = Validator::make($data, self::rules())->validate();

                $latest = (int) Plan::withTrashed()->where('code', $source->code)->lockForUpdate()->max('version');
                $features = $source->features()->orderBy('feature_id')->lockForUpdate()->get();

                if (! hash_equals($source->compositionState($features), $values['expected_state'])
                    || $latest !== (int) $values['expected_latest_version']) {
                    $this->fail('expected_state', 'Le plan ou ses versions ont changé. Rechargez avant de recommencer.');
                }

                if ($latest >= self::MAX_VERSION) {
                    $this->fail('plan', 'Le nombre maximal de versions est atteint.');
                }

                $catalog = Feature::withTrashed()->whereIn('id', $features->pluck('feature_id'))
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

                $unavailable = $features->contains(function (PlanFeature $feature) use ($catalog): bool {
                    $item = $catalog->get($feature->feature_id);

                    return $item === null || $item->trashed() || ! $item->is_active;
                });

                if ($unavailable) {
                    $this->fail('features', 'Une fonctionnalité du plan source est indisponible.');
                }

                $version = new Plan($source->only(self::COPIED_FIELDS));
                $version->forceFill(['version' => $latest + 1, 'is_active' => false]);

                try {
                    $version->save();
                } catch (UniqueConstraintViolationException) {
                    $this->fail('expected_latest_version', 'Une version a été créée. Rechargez avant de recommencer.');
                }

                foreach ($features as $feature) {
                    PlanFeature::create([
                        'plan_id' => $version->id,
                        'feature_id' => $feature->feature_id,
                        'is_active' => $feature->is_active,
                        'limit' => $feature->limit,
                    ]);
                }

                activity()->causedBy($actor)->performedOn($version)->event('plan_version_created')
                    ->withProperties([
                        'source_uuid' => $source->uuid,
                        'source_version' => $source->version,
                        'after' => $version->only([...self::COPIED_FIELDS, 'version', 'is_active']),
                        'features' => $features->map(fn (PlanFeature $feature): array => [
                            'uuid' => $catalog->get($feature->feature_id)->uuid,
                            'is_active' => $feature->is_active,
                            'limit' => $feature->limit,
                        ])->all(),
                    ])
                    ->log('Nouvelle version du plan créée.');

                return $version;
            });
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}