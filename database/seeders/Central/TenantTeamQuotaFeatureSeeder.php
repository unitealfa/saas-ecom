<?php

namespace Database\Seeders\Central;

use App\Enums\Central\Features\PeriodEnum;
use App\Enums\Central\Features\QuotaScopeEnum;
use App\Enums\Central\Features\ValueTypeEnum;
use App\Models\Central\Features\Feature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Référentiel commercial seulement : ne fixe aucune valeur dans plan_features.
 * Réexécutable ; ne réactive, ne désarchive et ne modifie aucun enregistrement existant.
 */
final class TenantTeamQuotaFeatureSeeder extends Seeder
{
    public function run(): void
    {
        if (tenancy()->initialized) {
            throw new LogicException('Les quotas commerciaux se créent uniquement en contexte central.');
        }

        DB::transaction(function (): void {
            foreach ([
                Feature::TEAM_CUSTOM_ROLES_CODE => ['Nombre de rôles personnalisés par boutique', 'rôles'],
                Feature::TEAM_MEMBERS_CODE => ['Nombre de membres par boutique', 'membres'],
            ] as $code => [$name, $unit]) {
                $feature = Feature::withTrashed()->firstOrCreate(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'value_type' => ValueTypeEnum::QUANTITY,
                        'quota_scope' => QuotaScopeEnum::TENANT,
                        'period' => PeriodEnum::LIFETIME,
                        'unit' => $unit,
                    ],
                );

                // Le modèle Feature crée les nouveautés inactives par défaut.
                if ($feature->wasRecentlyCreated) {
                    $feature->forceFill(['is_active' => true])->save();
                }

                if ($feature->trashed() || ! $feature->is_active
                    || $feature->value_type !== ValueTypeEnum::QUANTITY
                    || $feature->quota_scope !== QuotaScopeEnum::TENANT
                    || $feature->period !== PeriodEnum::LIFETIME) {
                    throw new LogicException(
                        "La fonctionnalité commerciale {$code} existe avec un état ou une portée incompatible. "
                        .'Ne pas la remplacer automatiquement : examiner son historique.',
                    );
                }
            }
        }, 3);
    }
}
