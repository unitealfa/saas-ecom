<?php

namespace App\Http\Requests\Central\Billing;

use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class BillingRuleActionRequest extends FormRequest
{
    private const array FORBIDDEN_FIELDS = [
        'id', 'uuid', 'record_type', 'code', 'version', 'trigger_event', 'parameters', 'policy_status',
        'numbering_scope', 'created_by_id', 'validated_by_id', 'validated_at', 'operation_key',
        'correlation_id', 'document_type', 'fiscal_year', 'prefix', 'next_number', 'sequence_slot',
    ];

    public function authorize(): bool
    {
        $actor = $this->user('central');
        $rule = $this->route('billingRule');
        $ability = $this->routeIs('*.validate') ? 'validateRule' : 'activate';

        return $actor instanceof User && $rule instanceof BillingRule && Gate::forUser($actor)->allows($ability, $rule);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $validating = $this->routeIs('*.validate');
        $retiring = $this->routeIs('*.retire');

        return [
            'expected_state' => ['required', 'string', 'regex:'.BillingRule::STATE_PATTERN],
            'validation_reference' => $validating ? ['required', 'string', 'max:2000'] : ['prohibited'],
            'effective_at' => $validating || $retiring ? ['prohibited'] : ['required', 'date'],
            'ends_at' => $validating ? ['prohibited'] : ($retiring ? ['required', 'date'] : ['nullable', 'date', 'after:effective_at']),
            'reason' => $retiring ? ['required', 'string', 'max:2000'] : ['prohibited'],
            ...array_fill_keys(self::FORBIDDEN_FIELDS, ['prohibited']),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'validation_reference.required' => 'Indiquez la référence qui justifie la validation de cette règle.',
            'reason.required' => 'Le motif du retrait est obligatoire.',
            'expected_state.required' => 'Rechargez le formulaire avant de recommencer.',
            'ends_at.after' => 'La fin doit être postérieure à la date d’effet.',
        ];
    }
}
