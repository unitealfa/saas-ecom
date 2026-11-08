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

    /** @return list<array<string, mixed>> */
    public function available(): array
    {
        abort_if(tenancy()->initialized, 403);
        if ((new Plan)->getConnection()->transactionLevel() > 0) {
            return $this->rows();
        }
        $store = Cache::store('file');
        $version = $store->get($this->key().':version', 'initial');

        return $store->remember($this->key().':'.$version, self::TTL, fn (): array => $this->rows());
    }

    public function invalidate(): void
    {
        $store = Cache::store('file');
        $previous = $store->get($this->key().':version', 'initial');
        $store->forever($this->key().':version', (string) Str::uuid());
        $store->forget($this->key().':'.$previous);
    }

    private function key(): string
    {
        return 'public-plans:v2:'.hash('sha256', (new Plan)->getConnection()->getDatabaseName());
    }

    /** @return list<array<string, mixed>> */
    private function rows(): array
    {
        return array_values(Plan::active()->with(['features' => fn ($query) => $query->where('is_active', true)
            ->whereIn('feature_id', Feature::active()->select('id'))->with('feature')->orderBy('id')])
            ->orderBy('code')->orderByDesc('version')
            ->get(['id', 'uuid', 'code', 'version', 'name', 'description', 'monthly_price', 'annual_price'])
            ->map(fn (Plan $plan): array => [...$plan->only(['uuid', 'code', 'version', 'name', 'description', 'monthly_price', 'annual_price']),
                'features' => $plan->features->map(fn (PlanFeature $feature): array => [
                    'name' => $feature->feature->name, 'value' => $feature->feature->value_type === ValueTypeEnum::BOOLEAN
                        ? 'Inclus' : ($feature->limit === null ? 'Non précisé' : (string) $feature->limit),
                ])->all()])->all());
    }
}
