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
    private const array FORBIDDEN_FIELDS = [
        'is_active', 'id', 'uuid', 'deleted_at', 'limit', 'plan_id',
    ];

    public function authorize(): bool
    {
        $actor = $this->user('central');
        $feature = $this->feature();

        return $actor instanceof User
            && Gate::forUser($actor)->allows($feature ? 'update' : 'create', $feature ?? Feature::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $feature = $this->feature();

        return [
            // Le code est immuable : à la modification, il doit rester celui de la fonctionnalité.
            'code' => $feature
                ? ['required', 'string', Rule::in([$feature->code])]
                : ['bail', 'required', 'string', 'max:255', 'regex:/^[a-z0-9][a-z0-9_.-]*$/D',
                    Rule::unique(Feature::class, 'code')],
            'name' => ['required', 'string', 'max:255'],
            'value_type' => ['required', Rule::enum(ValueTypeEnum::class)],
            'unit' => ['nullable', 'string', 'max:255'],
            'quota_scope' => ['required', Rule::enum(QuotaScopeEnum::class)],
            'period' => ['required', Rule::enum(PeriodEnum::class)],
            'expected_state' => $feature
                ? ['required', 'string', 'regex:'.Feature::STATE_PATTERN]
                : ['prohibited'],
            ...array_fill_keys(self::FORBIDDEN_FIELDS, ['prohibited']),
        ];
    }

    /** @return array{code: string, name: string, value_type: int|string, unit: ?string, quota_scope: int|string, period: int|string} */
    public function featureData(): array
    {
        return [
            'code' => $this->validated('code'),
            'name' => $this->validated('name'),
            'value_type' => $this->validated('value_type'),
            'unit' => $this->validated('unit'),
            'quota_scope' => $this->validated('quota_scope'),
            'period' => $this->validated('period'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = array_map(
            fn (mixed $value): mixed => is_string($value) ? trim($value) : $value,
            $this->only(['code', 'name', 'unit']),
        );

        if (is_string($values['code'] ?? null)) {
            $values['code'] = strtolower($values['code']);
        }

        if (($values['unit'] ?? null) === '') {
            $values['unit'] = null;
        }

        $this->merge($values);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.unique' => 'Ce code est déjà réservé, y compris pour une fonctionnalité archivée.',
            'code.regex' => 'Utilisez des lettres, chiffres, points, tirets ou underscores, par exemple local.products.',
            'code.in' => 'Le code d’une fonctionnalité ne peut pas être modifié.',
            'expected_state.required' => 'Rechargez le formulaire avant de recommencer.',
        ];
    }

    private function feature(): ?Feature
    {
        $feature = $this->route('feature');

        return $feature instanceof Feature ? $feature : null;
    }
}
