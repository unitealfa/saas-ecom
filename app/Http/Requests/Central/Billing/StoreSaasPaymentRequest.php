<?php

namespace App\Http\Requests\Central\Billing;

use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Users\User;
use App\Services\Central\Billing\PaymentDocumentService;
use App\Services\Central\Billing\SaasPaymentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreSaasPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $invoice = $this->route('invoice');

        return $actor instanceof User && $invoice instanceof SaasInvoice
            && Gate::forUser($actor)->allows($this->cash() ? 'recordCashPayment' : 'declarePayment', $invoice);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...SaasPaymentService::declarationRules($this->cash()),
            ...($this->cash() ? ['proof' => ['missing']] : ['proof' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:'.PaymentDocumentService::MAX_KB]])];
    }

    private function cash(): bool
    {
        return $this->route()?->getActionMethod() === 'cash';
    }
}
