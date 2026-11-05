<?php

namespace App\Http\Requests\Central\Billing;

use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReserveSaasInvoiceNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $invoice = $this->route('invoice');

        return $actor instanceof User && $invoice instanceof SaasInvoice
            && Gate::forUser($actor)->allows('reserveNumber', $invoice);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expected_state' => ['required', 'string', 'regex:'.BillingRule::STATE_PATTERN],
            ...array_fill_keys(['number', 'sequence_id', 'sequence_number', 'fiscal_year', 'status',
                'total_amount', 'net_amount', 'tax_amount', 'user_id', 'billing_rule_id'], ['prohibited']),
        ];
    }
}
