<?php

namespace App\Http\Requests\Central\Plans;

use App\Models\Central\Plans\Plan;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SetDefaultPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $plan = $this->route('plan');

        return $actor instanceof User && $plan instanceof Plan
            && Gate::forUser($actor)->allows('setDefault', $plan);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['expected_state' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'], 'confirmed' => ['required', 'accepted']];
    }
}
