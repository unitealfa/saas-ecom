<?php

namespace App\Services\Central\Plans;

use App\Enums\Central\Features\ValueTypeEnum;
use App\Models\Central\Features\Feature;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PublicPlanCatalog
{
    private const int TTL = 43200;

    private const array FIELDS = ['uuid', 'code', 'version', 'name', 'description', 'monthly_price', 'annual_price'];

    /** @return list<array<string, mixed>> */
    public function available(): array
    {
        abort_if(tenancy()->initialized, 403);

        // Dans une transaction, on lit toujours la base : le cache n'a pas encore vu les écritures en cours.
        if ((new Plan)->getConnection()->transactionLevel() > 0) {
            return $this->rows();
        }

        $store = Cache::store('file');
        $version = $store->get($this->versionKey(), 'initial');

        return $store->remember($this->entryKey($version), self::TTL, fn (): array => $this->rows());
    }

    /** Change la version du cache et supprime l'entrée précédente. */
    public function invalidate(): void
    {
        $store = Cache::store('file');
        $previous = $store->get($this->versionKey(), 'initial');

        $store->forever($this->versionKey(), (string) Str::uuid());
        $store->forget($this->entryKey($previous));
    }

    private function versionKey(): string
    {
        return $this->prefix().':version';
    }

    private function entryKey(string $version): string
    {
        return $this->prefix().':'.$version;
    }

    /** Le nom de la base évite de mélanger deux environnements. */
    private function prefix(): string
    {
        return 'public-plans:v2:'.hash('sha256', (new Plan)->getConnection()->getDatabaseName());
    }

    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        $plans = Plan::active()
            ->with(['features' => fn ($query) => $query
                ->where('is_active', true)
                ->whereIn('feature_id', Feature::active()->select('id'))
                ->with('feature')
                ->orderBy('id')])
            ->orderBy('code')->orderByDesc('version')
            ->get(['id', ...self::FIELDS]);

        return $plans->map(fn (Plan $plan): array => [
            ...$plan->only(self::FIELDS),
            'features' => $plan->features->map(fn (PlanFeature $feature): array => [
                'name' => $feature->feature->name,
                'value' => $this->valueLabel($feature),
            ])->all(),
        ])->all();
    }

    private function valueLabel(PlanFeature $feature): string
    {
        if ($feature->feature->value_type === ValueTypeEnum::BOOLEAN) {
            return 'Inclus';
        }

        return $feature->limit === null ? 'Non précisé' : (string) $feature->limit;
    }
}