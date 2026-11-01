<?php

namespace App\Http\Requests\Central\Plans;

use App\Models\Central\Plans\Plan;
use App\Services\Central\Plans\PlanCreationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('central'))->allows('create', Plan::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(PlanCreationService::normalize($this->all()));
    }

    public function rules(): array
    {
        return PlanCreationService::rules();
    }
}
