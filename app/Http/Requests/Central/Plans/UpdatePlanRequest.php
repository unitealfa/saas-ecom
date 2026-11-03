<?php

namespace App\Http\Requests\Central\Plans;

use App\Models\Central\Plans\Plan;
use App\Services\Central\Plans\PlanCreationService;
use App\Services\Central\Plans\PlanUpdateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $plan instanceof Plan && Gate::forUser($this->user('central'))->allows('update', $plan);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(PlanCreationService::normalize($this->all()));
    }

    public function rules(): array
    {
        return PlanUpdateService::rules();
    }
}
