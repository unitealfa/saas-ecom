<?php

namespace App\Http\Requests\Central\Billing;

use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Models\Central\Billing\BillingSequence;
use App\Models\Central\Users\User;
use App\Services\Central\Billing\SaasNumberingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class InitializeBillingSequenceRequest extends FormRequest
{
    private const array FORBIDDEN_FIELDS = [
        'next_number', 'prefix', 'sequence_slot', 'record_type', 'id', 'uuid', 'operation_key', 'correlation_id',
        'code', 'version', 'trigger_event', 'numbering_scope', 'parameters', 'policy_status',
        'validation_reference', 'created_by_id', 'validated_by_id', 'validated_at', 'effective_at', 'ends_at',
    ];

    public function authorize(): bool
    {
        $actor = $this->user('central');
        if (! $actor instanceof User) {
            return false;
        }
        $value = $this->input('document_type');
        $type = (is_int($value) || (is_string($value) && ctype_digit($value)))
            ? DocumentTypeEnum::tryFrom((int) $value) : null;
        $types = $type === null ? DocumentTypeEnum::cases() : [$type];
        foreach ($types as $candidate) {
            if (Gate::forUser($actor)->allows('initialize', [BillingSequence::class, $candidate])) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...SaasNumberingService::initializationRules(),
            ...array_fill_keys(self::FORBIDDEN_FIELDS, ['prohibited']),
        ];
    }
}
