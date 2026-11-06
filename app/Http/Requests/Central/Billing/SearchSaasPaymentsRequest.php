<?php

namespace App\Http\Requests\Central\Billing;

use App\Enums\Central\Billing\TransferStatusEnum;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SearchSaasPaymentsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user('central');

        return $actor instanceof User && Gate::forUser($actor)->allows($this->routeIs('owner.*', '*.owner.*') ? 'viewOwn' : 'viewAny', SaasPayment::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'integer', Rule::enum(TransferStatusEnum::class)]];
    }
}
