<?php

namespace App\Services\Central\Plans;

use App\Enums\Central\Features\ValueTypeEnum;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PlanCompositionService
{
    public function __construct(private readonly AuthorizationMutation $mutations) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'expected_state' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'features' => ['present', 'array', 'list'],
            'features.*' => ['required', 'array:uuid,is_active,limit'],
            'features.*.uuid' => ['required', 'string', 'uuid:4', 'distinct'],
            'features.*.is_active' => ['required', 'boolean'],
            'features.*.limit' => ['present', 'nullable', 'integer', 'min:0', 'max:9223372036854775807'],
        ];
    }

    /** @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        if (isset($data['features']) && is_array($data['features'])) {
            foreach ($data['features'] as $key => $feature) {
                if (is_array($feature) && isset($feature['uuid']) && is_string($feature['uuid'])) {
                    $data['features'][$key]['uuid'] = strtolower(trim($feature['uuid']));
                }
            }
        }

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    public function update(User $actor, Plan $plan, array $data): void
    {
        $this->mutations->run($actor, 'updateFeatures',
            fn (): Plan => Plan::whereKey($plan->id)->lockForUpdate()->firstOrFail(),
            function (Plan $plan) use ($actor, $data): void {
                $values = Validator::make(self::normalize($data), self::rules())->validate();
                $current = $plan->features()->orderBy('feature_id')->lockForUpdate()->get();
                if (! hash_equals($plan->compositionState($current), $values['expected_state'])) {
                    $this->fail('expected_state', 'Le plan a changé. Rechargez avant de recommencer.');
                }
                if (DB::table('subscriptions')->where('plan_id', $plan->id)->lockForUpdate()->first(['id']) !== null) {
                    $this->fail('plan', 'Cette version est déjà utilisée par un abonnement et ne peut plus être modifiée.');
                }

                $items = $values['features'];
                $uuids = array_column($items, 'uuid');
                $locked = Feature::withTrashed()->where(function (Builder $query) use ($current, $uuids): void {
                    $query->whereIn('id', $current->pluck('feature_id'))->orWhereIn('uuid', $uuids);
                })->orderBy('id')->lockForUpdate()->get();
                $byUuid = $locked->keyBy('uuid');
                $byId = $locked->keyBy('id');
                $currentByFeature = $current->keyBy('feature_id');
                $before = $current->map(fn (PlanFeature $item): array => [
                    'uuid' => $byId->get($item->feature_id)->uuid,
                    'is_active' => $item->is_active,
                    'limit' => $item->limit,
                ])->sortBy('uuid')->values()->all();

                $desired = [];
                foreach ($items as $input) {
                    $feature = $byUuid->get($input['uuid']);
                    if (! $feature instanceof Feature) {
                        $this->fail('features', 'Une fonctionnalité demandée est introuvable.');
                    }
                    $enabled = (bool) $input['is_active'];
                    $limit = $input['limit'] === null ? null : (int) $input['limit'];
                    $previous = $currentByFeature->get($feature->id);
                    if (($feature->trashed() || ! $feature->is_active)
                        && ($previous === null || $previous->is_active !== $enabled || $previous->limit !== $limit)) {
                        $this->fail('features', 'Une fonctionnalité indisponible ne peut pas être ajoutée ou modifiée.');
                    }
                    if ($feature->value_type === ValueTypeEnum::BOOLEAN && $limit !== null) {
                        $this->fail('features', 'Une fonctionnalité oui/non ne peut pas avoir de limite numérique.');
                    }
                    $desired[$feature->id] = ['uuid' => $feature->uuid, 'is_active' => $enabled, 'limit' => $limit];
                }

                $after = collect($desired)->sortBy('uuid')->values()->all();
                if ($before === $after) {
                    return;
                }

                foreach ($current as $item) {
                    if (! array_key_exists($item->feature_id, $desired)) {
                        $item->delete();
                    }
                }
                foreach ($desired as $featureId => $configuration) {
                    $item = $currentByFeature->get($featureId) ?? new PlanFeature(['plan_id' => $plan->id, 'feature_id' => $featureId]);
                    $item->fill(['is_active' => $configuration['is_active'], 'limit' => $configuration['limit']]);
                    if (! $item->exists || $item->isDirty()) {
                        $item->save();
                    }
                }

                activity()->causedBy($actor)->performedOn($plan)->event('plan_features_updated')
                    ->withProperties(['before' => $before, 'after' => $after])
                    ->log('Fonctionnalités et quotas du plan modifiés.');
            });
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
