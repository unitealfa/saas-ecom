<?php

namespace App\Http\Requests\Central\Plans;

use App\Models\Central\Plans\Plan;
use App\Services\Central\Plans\PlanActivationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdatePlanActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $plan instanceof Plan && Gate::forUser($this->user('central'))->allows('activate', $plan);
    }

    public function rules(): array
    {
        return PlanActivationService::rules();
    }
}
