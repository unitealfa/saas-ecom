<?php

namespace App\Http\Requests\Central\Billing;

use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class IssueSaasInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $invoice = $this->route('invoice');

        return $actor instanceof User && $invoice instanceof SaasInvoice && Gate::forUser($actor)->allows('issue', $invoice);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['expected_state' => ['required', 'string', 'regex:'.BillingRule::STATE_PATTERN],
            ...array_fill_keys(['number', 'amount', 'net_amount', 'total_amount', 'taxes', 'status', 'document_media_id', 'operation_key', 'user_id'], ['missing'])];
    }
}
