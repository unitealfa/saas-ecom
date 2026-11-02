<?php

namespace App\Http\Requests\Central\Plans;

use App\Models\Central\Plans\Plan;
use App\Services\Central\Plans\PlanCompositionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdatePlanFeaturesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $plan instanceof Plan && Gate::forUser($this->user('central'))->allows('updateFeatures', $plan);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(PlanCompositionService::normalize($this->all()));
    }

    public function rules(): array
    {
        return PlanCompositionService::rules();
    }
}
