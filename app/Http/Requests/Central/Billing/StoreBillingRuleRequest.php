<?php

namespace App\Http\Requests\Central\Billing;

use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Users\User;
use App\Services\Central\Billing\BillingRuleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreBillingRuleRequest extends FormRequest
{
    private const array FORBIDDEN_FIELDS = [
        'id', 'uuid', 'record_type', 'version', 'policy_status', 'created_by_id', 'validated_by_id',
        'validated_at', 'validation_reference', 'numbering_scope', 'operation_key', 'correlation_id',
        'document_type', 'fiscal_year', 'prefix', 'next_number', 'sequence_slot', 'effective_at', 'ends_at',
    ];

    public function authorize(): bool
    {
        $actor = $this->user('central');
        $rule = $this->rule();
        $ability = $this->routeIs('*.versions.store') ? 'createVersion' : ($rule ? 'update' : 'create');

        return $actor instanceof User && Gate::forUser($actor)->allows($ability, $rule ?? BillingRule::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = BillingRuleService::contentRules();
        if ($this->rule() !== null) {
            $rules['code'] = ['required', 'string', Rule::in([$this->rule()->code])];
        }

        return [
            ...$rules,
            'expected_state' => $this->rule() ? ['required', 'string', 'regex:'.BillingRule::STATE_PATTERN] : ['prohibited'],
            'operation_uuid' => $this->isMethod('PUT') ? ['prohibited'] : ['required', 'uuid:4'],
            ...array_fill_keys(self::FORBIDDEN_FIELDS, ['missing']),
        ];
    }

    /** @return array<string, mixed> */
    public function ruleData(): array
    {
        return $this->safe()->only(['code', 'trigger_event', 'parameters']);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.in' => 'Le code de la règle est permanent.',
            'expected_state.required' => 'Rechargez le formulaire avant de recommencer.',
            'parameters.document_type.in' => 'Cette règle produit une facture.',
            'operation_uuid.required' => 'Rechargez le formulaire de création.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtolower(trim($this->input('code')))]);
        }
    }

    private function rule(): ?BillingRule
    {
        $rule = $this->route('billingRule');

        return $rule instanceof BillingRule ? $rule : null;
    }
}
