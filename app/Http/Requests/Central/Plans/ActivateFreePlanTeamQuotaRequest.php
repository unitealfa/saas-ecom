<?php

namespace App\Http\Requests\Central\Plans;

use App\Models\Central\Plans\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ActivateFreePlanTeamQuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $plan instanceof Plan
            && Gate::forUser($this->user('central'))->allows('activateTeamQuotaVersion', $plan);
    }

    public function rules(): array
    {
        return ['expected_state' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']];
    }
}
