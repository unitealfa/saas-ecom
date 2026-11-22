<?php

namespace App\Services\Central\Plans;

use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
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

    public function __construct(private readonly AuthorizationMutation $mutations, private readonly PlanCompositionService $composition) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'expected_state' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'expected_latest_version' => ['required', 'integer', 'min:1', 'max:'.self::MAX_VERSION],
            ...array_fill_keys(self::FORBIDDEN_FIELDS, ['prohibited']),
        ];
    }

    public static function suggestedName(Plan $source, int $version): string
    {
        $previousSuffix = ' — v'.$source->version;
        $name = str_ends_with($source->name, $previousSuffix)
            ? mb_substr($source->name, 0, -mb_strlen($previousSuffix))
            : $source->name;
        $suffix = ' — v'.$version;

        return rtrim(mb_substr($name, 0, 255 - mb_strlen($suffix))).$suffix;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{information?: array<string, mixed>, features?: list<array<string, mixed>>, confirmed_copy?: bool}|null  $draft
     */
    public function create(User $actor, Plan $source, array $data, ?array $draft = null): Plan
    {
        return $this->mutations->run($actor, 'createVersion',
            fn (): Plan => Plan::whereKey($source->id)->lockForUpdate()->firstOrFail(),
            function (Plan $source) use ($actor, $data, $draft): Plan {
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

                $version = new Plan($source->only(self::COPIED_FIELDS));
                if ($draft !== null) {
                    $version->name = self::suggestedName($source, $latest + 1);
                }
                if (isset($draft['information'])) {
                    Gate::forUser($actor)->authorize('update', $source);
                    $information = Validator::make(PlanCreationService::normalize($draft['information']),
                        Arr::only(PlanCreationService::rules(), ['name', 'description', 'monthly_price', 'annual_price']))->validate();
                    $version->fill($information);
                }
                if (isset($draft['features'])) {
                    Gate::forUser($actor)->authorize('updateVersionFeatures', $source);
                }
                $configuration = $this->composition->versionConfiguration($features, $draft['features'] ?? null);
                $original = $features->mapWithKeys(fn (PlanFeature $feature): array => [
                    $feature->feature_id => [$feature->is_active, $feature->limit],
                ])->all();
                $desired = array_map(fn (array $feature): array => [$feature['is_active'], $feature['limit']], $configuration);
                $details = $version->only(self::COPIED_FIELDS);
                if ($version->name === self::suggestedName($source, $latest + 1)) {
                    $details['name'] = $source->name;
                }
                if ($draft !== null && ! ($draft['confirmed_copy'] ?? false)
                    && $details === $source->only(self::COPIED_FIELDS)
                    && $original === $desired) {
                    $this->fail('copy_confirmation', 'Voulez-vous créer une copie exacte de cette version du plan ?');
                }
                $version->forceFill(['version' => $latest + 1, 'is_active' => false]);

                try {
                    $version->save();
                } catch (UniqueConstraintViolationException) {
                    $this->fail('expected_latest_version', 'Une version a été créée. Rechargez avant de recommencer.');
                }

                foreach ($configuration as $featureId => $feature) {
                    PlanFeature::create([
                        'plan_id' => $version->id,
                        'feature_id' => $featureId,
                        'is_active' => $feature['is_active'],
                        'limit' => $feature['limit'],
                    ]);
                }

                activity()->causedBy($actor)->performedOn($version)->event('plan_version_created')
                    ->withProperties([
                        'source_uuid' => $source->uuid,
                        'source_version' => $source->version,
                        'after' => $version->only([...self::COPIED_FIELDS, 'version', 'is_active']),
                        'features' => array_values($configuration),
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
