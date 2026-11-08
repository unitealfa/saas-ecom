<?php

namespace App\Http\Requests\Central\Billing;

use App\Models\Central\Billing\SaasDocumentDelivery;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RetrySaasDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $delivery = $this->route('delivery');

        return $actor instanceof User && $delivery instanceof SaasDocumentDelivery
            && Gate::forUser($actor)->allows('retry', $delivery);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['expected_state' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']];
    }
}
