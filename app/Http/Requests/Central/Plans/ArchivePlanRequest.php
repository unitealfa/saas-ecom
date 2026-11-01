<?php

namespace App\Http\Requests\Central\Plans;

use App\Models\Central\Plans\Plan;
use App\Services\Central\Plans\PlanArchiveService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ArchivePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $plan instanceof Plan && Gate::forUser($this->user('central'))->allows('archive', $plan);
    }

    public function rules(): array
    {
        return PlanArchiveService::rules();
    }
}
