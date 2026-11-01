<?php

namespace App\Http\Controllers\Central\Admin\Plans;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Plans\SearchPlansRequest;
use App\Http\Requests\Central\Plans\StorePlanRequest;
use App\Http\Requests\Central\Plans\UpdatePlanFeaturesRequest;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Plans\PlanFeature;
use App\Services\Central\Plans\PlanCatalogService;
use App\Services\Central\Plans\PlanCreationService;
use App\Services\Central\Plans\PlanCompositionService;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function __construct(
        private readonly PlanCatalogService $plans,
        private readonly PlanCreationService $creation,
        private readonly PlanCompositionService $composition,
    ) {}

    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = $this->creation->create($this->centralActor(), $request->validated());

        return response()->json(['data' => ['uuid' => $plan->uuid]], 201)
            ->header('Cache-Control', 'private, no-store');
    }

    public function index(SearchPlansRequest $request): JsonResponse
    {
        $plans = $this->plans->paginate($this->centralActor(), $request->validated('search'));

        return response()->json([
            'data' => $plans->getCollection()->map(fn (Plan $plan): array => $this->planData($plan))->values(),
            'meta' => [
                'current_page' => $plans->currentPage(),
                'last_page' => $plans->lastPage(),
                'per_page' => $plans->perPage(),
                'total' => $plans->total(),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function show(Plan $plan): JsonResponse
    {
        $plan = $this->plans->details($this->centralActor(), $plan);
        $data = $this->planData($plan);
        $data['expected_state'] = $plan->compositionState($plan->features);
        $data['features'] = $plan->features->map(fn (PlanFeature $item): array => [
            'uuid' => $item->feature->uuid,
            'code' => $item->feature->code,
            'name' => $item->feature->name,
            'value_type' => $item->feature->value_type->value,
            'unit' => $item->feature->unit,
            'quota_scope' => $item->feature->quota_scope->value,
            'period' => $item->feature->period->value,
            'is_active' => $item->is_active,
            'limit' => $item->limit,
        ])->values();

        return response()->json(['data' => $data])->header('Cache-Control', 'private, no-store');
    }

    public function updateFeatures(UpdatePlanFeaturesRequest $request, Plan $plan): JsonResponse
    {
        $this->composition->update($this->centralActor(), $plan, $request->validated());

        return response()->json(null, 204)->header('Cache-Control', 'private, no-store');
    }

    /** @return array<string, mixed> */
    private function planData(Plan $plan): array
    {
        return [
            'uuid' => $plan->uuid,
            'code' => $plan->code,
            'version' => $plan->version,
            'name' => $plan->name,
            'description' => $plan->description,
            'monthly_price' => $plan->monthly_price,
            'annual_price' => $plan->annual_price,
            'is_active' => $plan->is_active,
            'archived_at' => $plan->deleted_at?->toISOString(),
        ];
    }
}
