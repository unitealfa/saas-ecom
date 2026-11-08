<?php

namespace App\Services\Central\Plans;

use App\Enums\Central\Features\ValueTypeEnum;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
            'features.*.limit' => ['present', 'nullable', 'integer', 'min:0', 'max:'.PHP_INT_MAX],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        if (! is_array($data['features'] ?? null)) {
            return $data;
        }

        foreach ($data['features'] as $key => $feature) {
            if (is_string($feature['uuid'] ?? null)) {
                $data['features'][$key]['uuid'] = strtolower(trim($feature['uuid']));
            }
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
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

                // Une version déjà souscrite est figée : toute modification passe par une nouvelle version.
                if (DB::table('subscriptions')->where('plan_id', $plan->id)->lockForUpdate()->value('id') !== null) {
                    $this->fail('plan', 'Cette version est déjà utilisée par un abonnement et ne peut plus être modifiée.');
                }

                $features = $this->lockFeatures($current, array_column($values['features'], 'uuid'));
                $currentByFeature = $current->keyBy('feature_id');

                $before = $current->map(fn (PlanFeature $item): array => [
                    'uuid' => $features->get($item->feature_id)->uuid,
                    'is_active' => $item->is_active,
                    'limit' => $item->limit,
                ])->sortBy('uuid')->values()->all();

                $desired = $this->desired($values['features'], $features->keyBy('uuid'), $currentByFeature);
                $after = collect($desired)->sortBy('uuid')->values()->all();

                if ($before === $after) {
                    return;
                }

                $this->sync($plan, $current, $currentByFeature, $desired);

                activity()->causedBy($actor)->performedOn($plan)->event('plan_features_updated')
                    ->withProperties(['before' => $before, 'after' => $after])
                    ->log('Fonctionnalités et quotas du plan modifiés.');
            });
    }

    /**
     * Verrouille les fonctionnalités actuelles du plan et celles demandées (archivées comprises).
     *
     * @param  Collection<int, PlanFeature>  $current
     * @param  list<string>  $uuids
     * @return Collection<int, Feature> Indexée par id.
     */
    private function lockFeatures(Collection $current, array $uuids): Collection
    {
        return Feature::withTrashed()
            ->where(fn (Builder $query) => $query
                ->whereIn('id', $current->pluck('feature_id'))
                ->orWhereIn('uuid', $uuids))
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    /**
     * Calcule la configuration voulue, par id de fonctionnalité, en contrôlant chaque ligne demandée.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  Collection<string, Feature>  $byUuid
     * @param  Collection<int, PlanFeature>  $currentByFeature
     * @return array<int, array{uuid: string, is_active: bool, limit: ?int}>
     */
    private function desired(array $items, Collection $byUuid, Collection $currentByFeature): array
    {
        $desired = [];

        foreach ($items as $input) {
            $feature = $byUuid->get($input['uuid'])
                ?? $this->fail('features', 'Une fonctionnalité demandée est introuvable.');

            $enabled = (bool) $input['is_active'];
            $limit = $input['limit'] === null ? null : (int) $input['limit'];
            $previous = $currentByFeature->get($feature->id);

            $changed = $previous === null || $previous->is_active !== $enabled || $previous->limit !== $limit;

            if (($feature->trashed() || ! $feature->is_active) && $changed) {
                $this->fail('features', 'Une fonctionnalité indisponible ne peut pas être ajoutée ou modifiée.');
            }

            if ($feature->value_type === ValueTypeEnum::BOOLEAN && $limit !== null) {
                $this->fail('features', 'Une fonctionnalité oui/non ne peut pas avoir de limite numérique.');
            }

            $desired[$feature->id] = ['uuid' => $feature->uuid, 'is_active' => $enabled, 'limit' => $limit];
        }

        return $desired;
    }

    /**
     * Supprime les fonctionnalités retirées, puis crée ou met à jour les autres.
     *
     * @param  Collection<int, PlanFeature>  $current
     * @param  Collection<int, PlanFeature>  $currentByFeature
     * @param  array<int, array{uuid: string, is_active: bool, limit: ?int}>  $desired
     */
    private function sync(Plan $plan, Collection $current, Collection $currentByFeature, array $desired): void
    {
        foreach ($current as $item) {
            if (! array_key_exists($item->feature_id, $desired)) {
                $item->delete();
            }
        }

        foreach ($desired as $featureId => $configuration) {
            $item = $currentByFeature->get($featureId)
                ?? new PlanFeature(['plan_id' => $plan->id, 'feature_id' => $featureId]);

            $item->fill(['is_active' => $configuration['is_active'], 'limit' => $configuration['limit']]);

            if (! $item->exists || $item->isDirty()) {
                $item->save();
            }
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}