<?php

namespace App\Http\Requests\Central\Billing;

use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CreateInstallmentInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');

        return $actor instanceof User && Gate::forUser($actor)->allows('create', SaasInvoice::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['billing_rule_uuid' => ['required', 'uuid:4'], 'expected_state' => ['required', 'string', 'regex:'.BillingRule::STATE_PATTERN],
            ...array_fill_keys(['amount', 'net_amount', 'tax_amount', 'total_amount', 'taxes', 'number', 'user_id', 'operation_key', 'status', 'document_type', 'currency'], ['missing'])];
    }
}
