<?php

namespace App\Services\Central\Plans;

use App\Enums\Central\Features\PeriodEnum;
use App\Enums\Central\Features\QuotaScopeEnum;
use App\Enums\Central\Features\ValueTypeEnum;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\AuthorizationMutation;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Exception volontaire et étroite à la protection du plan gratuit : seuls les deux
 * quotas d'équipe sont éditables, via une NOUVELLE version sans toucher aux souscriptions.
 */
final class FreePlanTeamQuotaService
{
    public function __construct(
        private readonly AuthorizationMutation $mutations,
    ) {}

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'expected_state' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'expected_latest_version' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'max_roles' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'max_members' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ];
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, Plan $source, array $data): Plan
    {
        return $this->mutations->run($actor, 'configureTeamQuotas',
            fn (): Plan => Plan::whereKey($source->id)->lockForUpdate()->firstOrFail(),
            function (Plan $source) use ($actor, $data): Plan {
                $values = Validator::make($data, self::rules())->validate();

                // Les versions de l'offre gratuite sont verrouillées ensemble.
                $versions = Plan::withTrashed()->where('code', 'free')
                    ->orderBy('id')->lockForUpdate()->get();
                $latestVersion = (int) $versions->max('version');
                $current = $this->activeFreePlan();
                if ($source->id !== $current->id || ! $source->is_active
                    || $latestVersion !== (int) $values['expected_latest_version']) {
                    $this->fail('expected_state', 'Version gratuite modifiée. Rechargez le formulaire.');
                }
                $composition = $source->features()->orderBy('feature_id')->lockForUpdate()->get();
                if (! hash_equals($source->compositionState($composition), $values['expected_state'])) {
                    $this->fail('expected_state', 'Composition modifiée. Rechargez le formulaire.');
                }
                $sourceFeatures = Feature::withTrashed()->whereIn('id', $composition->pluck('feature_id'))
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($composition as $row) {
                    $sourceFeature = $sourceFeatures->get($row->feature_id);
                    if ($sourceFeature === null || $sourceFeature->trashed() || ! $sourceFeature->is_active) {
                        $this->fail('features', 'Une fonctionnalité de la version gratuite est indisponible.');
                    }
                }

                $features = Feature::whereIn('code', [
                    Feature::TEAM_CUSTOM_ROLES_CODE, Feature::TEAM_MEMBERS_CODE,
                ])->orderBy('id')->lockForUpdate()->get()->keyBy('code');
                $wanted = [];
                foreach ([
                    Feature::TEAM_CUSTOM_ROLES_CODE => (int) $values['max_roles'],
                    Feature::TEAM_MEMBERS_CODE => (int) $values['max_members'],
                ] as $code => $limit) {
                    $feature = $features->get($code);
                    if ($feature === null || ! $feature->is_active
                        || $feature->value_type !== ValueTypeEnum::QUANTITY
                        || $feature->quota_scope !== QuotaScopeEnum::TENANT
                        || $feature->period !== PeriodEnum::LIFETIME) {
                        $this->fail('features', 'La fonctionnalité '.$code.' est absente ou mal configurée.');
                    }
                    $wanted[$feature->id] = $limit;
                }

                $old = $composition->keyBy('feature_id');
                $same = true;
                foreach ($wanted as $featureId => $limit) {
                    $previous = $old->get($featureId);
                    if ($previous === null || ! $previous->is_active || $previous->limit !== $limit) {
                        $same = false;
                    }
                }
                if ($same) {
                    return $source;
                }
                if ($latestVersion >= 2147483647) {
                    $this->fail('expected_latest_version', 'Nombre de versions maximal atteint.');
                }

                // Copier toutes les autres fonctionnalités sans aucune modification.
                $next = new Plan($source->only([
                    'code', 'name', 'description', 'monthly_price', 'annual_price',
                ]));
                $next->forceFill(['version' => $latestVersion + 1, 'is_active' => false])->save();

                foreach ($composition as $row) {
                    PlanFeature::create([
                        'plan_id' => $next->id,
                        'feature_id' => $row->feature_id,
                        'is_active' => array_key_exists($row->feature_id, $wanted) ? true : $row->is_active,
                        'limit' => $wanted[$row->feature_id] ?? $row->limit,
                    ]);
                }
                foreach ($wanted as $featureId => $limit) {
                    if (! $old->has($featureId)) {
                        PlanFeature::create([
                            'plan_id' => $next->id,
                            'feature_id' => $featureId,
                            'is_active' => true,
                            'limit' => $limit,
                        ]);
                    }
                }

                // Conserver cette version INACTIVE tant qu'une activation distincte n'est pas autorisée.
                activity()->causedBy($actor)->performedOn($next)
                    ->event('free_plan_team_quotas_versioned')
                    ->withProperties([
                        'source_uuid' => $source->uuid,
                        'source_version' => $source->version,
                        'roles_limit' => $values['max_roles'],
                        'members_limit' => $values['max_members'],
                    ])->log('Brouillon de version gratuite créé avec quotas équipe ; souscriptions conservées.');

                return $next;
            });
    }

    public function activate(User $actor, Plan $draft, string $expected): Plan
    {
        return $this->mutations->run($actor, 'activateTeamQuotaVersion',
            fn (): Plan => Plan::whereKey($draft->id)->lockForUpdate()->firstOrFail(),
            function (Plan $draft) use ($actor, $expected): Plan {
                $versions = Plan::withTrashed()->where('code', 'free')
                    ->orderBy('id')->lockForUpdate()->get();
                $current = $this->activeFreePlan();
                if ($draft->trashed() || $draft->is_active || $draft->code !== 'free'
                    || (int) $draft->version !== (int) $versions->max('version')) {
                    $this->fail('plan', 'Seule la dernière version gratuite inactive peut être activée.');
                }
                $draftRows = $draft->features()->orderBy('feature_id')->lockForUpdate()->get();
                if (! hash_equals($draft->compositionState($draftRows), $expected)) {
                    $this->fail('expected_state', 'Le brouillon a changé. Rechargez sa fiche.');
                }
                foreach (['code', 'name', 'description', 'monthly_price', 'annual_price'] as $field) {
                    if ($draft->getAttribute($field) !== $current->getAttribute($field)) {
                        $this->fail('plan', 'Les informations de l’offre gratuite ne doivent pas changer.');
                    }
                }

                $original = $current->features()->orderBy('feature_id')->lockForUpdate()->get()->keyBy('feature_id');
                $proposed = $draftRows->keyBy('feature_id');
                $team = Feature::whereIn('code', [
                    Feature::TEAM_CUSTOM_ROLES_CODE, Feature::TEAM_MEMBERS_CODE,
                ])->orderBy('id')->lockForUpdate()->get()->keyBy('code');
                $teamIds = [];
                foreach ([Feature::TEAM_CUSTOM_ROLES_CODE, Feature::TEAM_MEMBERS_CODE] as $code) {
                    $feature = $team->get($code);
                    if ($feature === null || ! $feature->is_active
                        || $feature->value_type !== ValueTypeEnum::QUANTITY
                        || $feature->quota_scope !== QuotaScopeEnum::TENANT
                        || $feature->period !== PeriodEnum::LIFETIME) {
                        $this->fail('features', 'Quota commercial d’équipe invalide : '.$code);
                    }
                    $teamIds[] = $feature->id;
                    $grant = $proposed->get($feature->id);
                    if ($grant === null || ! $grant->is_active || $grant->limit === null || $grant->limit < 0) {
                        $this->fail('features', 'Les deux limites d’équipe doivent être définies avant activation.');
                    }
                }
                foreach ($original as $featureId => $row) {
                    if (in_array($featureId, $teamIds, true)) {
                        continue;
                    }
                    $copy = $proposed->get($featureId);
                    if ($copy === null || $copy->is_active !== $row->is_active || $copy->limit !== $row->limit) {
                        $this->fail('features', 'Une fonctionnalité hors équipe a changé.');
                    }
                }
                foreach ($proposed as $featureId => $row) {
                    if (! in_array($featureId, $teamIds, true) && ! $original->has($featureId)) {
                        $this->fail('features', 'Une fonctionnalité non autorisée a été ajoutée.');
                    }
                }

                // Revalidation du quota shops=2 sur la version source par FreePlanCatalog.
                // Toutes les fonctionnalités hors équipe y compris shops sont copiées à l'identique.
                $wasDefault = (bool) $current->is_default;
                $current->forceFill(['is_active' => false, 'is_default' => null])->save();
                $draft->forceFill(['is_active' => true, 'is_default' => $wasDefault ? true : null])->save();
                activity()->causedBy($actor)->performedOn($draft)
                    ->event('free_plan_team_quotas_activated')
                    ->withProperties(['replaces_uuid' => $current->uuid])
                    ->log('Version gratuite des quotas équipe activée, version antérieure conservée.');

                return $draft;
            });
    }

    private function activeFreePlan(): Plan
    {
        $plan = Plan::where('code', 'free')->where('is_active', true)->orderByDesc('version')->first();
        if ($plan === null || $plan->monthly_price !== '0.00' || $plan->annual_price !== '0.00'
            || ! $plan->features()->where('is_active', true)->where('limit', 2)
                ->whereHas('feature', fn ($query) => $query->where('code', Feature::SHOPS_CODE)->where('is_active', true))->exists()) {
            $this->fail('plan', 'L’offre gratuite active ou son quota de boutiques est invalide.');
        }

        return $plan;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
