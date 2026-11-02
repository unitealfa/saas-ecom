<?php

namespace App\Http\Requests\Central\Plans;

use App\Models\Central\Plans\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdatePlanPageFeaturesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $plan instanceof Plan && Gate::forUser($this->user('central'))->allows('updateFeatures', $plan);
    }

    public function rules(): array
    {
        return [
            'expected_state' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'selected' => ['sometimes', 'array'],
            'selected.*' => ['required', 'string', 'uuid:4', 'distinct'],
            'enabled' => ['sometimes', 'array'],
            'limits' => ['sometimes', 'array'],
        ];
    }

    public function composition(): array
    {
        $data = $this->validated();
        $features = [];
        foreach ($data['selected'] ?? [] as $uuid) {
            $limit = $data['limits'][$uuid] ?? null;
            $features[] = ['uuid' => $uuid, 'is_active' => isset($data['enabled'][$uuid]), 'limit' => $limit === '' ? null : $limit];
        }

        return ['expected_state' => $data['expected_state'], 'features' => $features];
    }
}
