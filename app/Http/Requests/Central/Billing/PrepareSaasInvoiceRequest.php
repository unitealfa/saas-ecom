<?php

namespace App\Http\Requests\Central\Billing;

use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Users\User;
use App\Services\Central\Billing\SaasInvoiceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PrepareSaasInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $subscription = $this->route('subscription');

        return $actor instanceof User && $subscription instanceof Subscription
            && Gate::forUser($actor)->allows('createInstallment', $subscription)
            && Gate::forUser($actor)->allows('create', SaasInvoice::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return SaasInvoiceService::rules();
    }

    protected function prepareForValidation(): void
    {
        foreach (['issuer_name', 'no_tax_reason'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => trim($this->input($key))]);
            }
        }
    }
}
