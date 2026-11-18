<?php

namespace App\Http\Requests\Central\Plans;

use App\Models\Central\Plans\Plan;
use App\Services\Central\Plans\FreePlanTeamQuotaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class SaveFreePlanTeamQuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $plan instanceof Plan
            && Gate::forUser($this->user('central'))->allows('configureTeamQuotas', $plan);
    }

    public function rules(): array
    {
        return FreePlanTeamQuotaService::rules();
    }
}
