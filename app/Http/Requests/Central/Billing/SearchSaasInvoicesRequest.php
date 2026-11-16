<?php

namespace App\Http\Requests\Central\Billing;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\InvoiceListFilterEnum;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SearchSaasInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');

        return $actor instanceof User && Gate::forUser($actor)->allows(
            $this->routeIs('owner.invoices.*', '*.owner.invoices.*') ? 'viewOwn' : 'viewBillingDossiers', SaasInvoice::class,
        );
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:255'], 'owner' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'integer', Rule::enum(DocumentStatusEnum::class)],
            'filter' => ['nullable', Rule::enum(InvoiceListFilterEnum::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'period_start' => ['nullable', 'required_with:period_end', 'date'],
            'period_end' => ['nullable', 'required_with:period_start', 'date', 'after:period_start']];
    }
}
