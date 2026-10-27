<?php

namespace App\Http\Requests\Central\Plans;

use App\Enums\Central\Features\PeriodEnum;
use App\Enums\Central\Features\QuotaScopeEnum;
use App\Enums\Central\Features\ValueTypeEnum;
use App\Models\Central\Features\Feature;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $feature = $this->route('feature');

        return $actor instanceof User
            && Gate::forUser($actor)->allows($feature instanceof Feature ? 'update' : 'create', $feature ?? Feature::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $feature = $this->route('feature');

        return [
            'code' => ['bail', 'required', 'string', 'max:255', 'regex:/^[a-z0-9][a-z0-9_.-]*$/D',
                Rule::unique(Feature::class, 'code')->ignore($feature)],
            'name' => ['required', 'string', 'max:255'],
            'value_type' => ['required', Rule::enum(ValueTypeEnum::class)],
            'unit' => ['nullable', 'string', 'max:255'],
            'quota_scope' => ['required', Rule::enum(QuotaScopeEnum::class)],
            'period' => ['required', Rule::enum(PeriodEnum::class)],
            'expected_state' => $feature instanceof Feature ? ['required', 'string', 'regex:/^[a-f0-9]{64}$/'] : ['prohibited'],
            'is_active' => ['prohibited'], 'id' => ['prohibited'], 'uuid' => ['prohibited'],
            'deleted_at' => ['prohibited'], 'limit' => ['prohibited'], 'plan_id' => ['prohibited'],
        ];
    }

    /** @return array{code: string, name: string, value_type: int|string, unit: ?string, quota_scope: int|string, period: int|string} */
    public function featureData(): array
    {
        return [
            'code' => $this->validated('code'), 'name' => $this->validated('name'),
            'value_type' => $this->validated('value_type'), 'unit' => $this->validated('unit'),
            'quota_scope' => $this->validated('quota_scope'), 'period' => $this->validated('period'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['code', 'name', 'unit'] as $field) {
            $value = $this->input($field);
            if (! is_string($value)) {
                continue;
            }
            $value = trim($value);
            $values[$field] = $field === 'code' ? strtolower($value) : ($field === 'unit' && $value === '' ? null : $value);
        }
        $this->merge($values);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.unique' => 'Ce code est déjà réservé, y compris pour une fonctionnalité archivée.',
            'code.regex' => 'Utilisez des lettres, chiffres, points, tirets ou underscores, par exemple local.products.',
            'expected_state.required' => 'Rechargez le formulaire avant de recommencer.',
        ];
    }
}
