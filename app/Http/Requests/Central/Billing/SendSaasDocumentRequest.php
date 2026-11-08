<?php

namespace App\Http\Requests\Central\Billing;

use App\Models\Central\Billing\SaasDocumentDelivery;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SendSaasDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');

        return $actor instanceof User && Gate::forUser($actor)->allows('send', SaasDocumentDelivery::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'document_uuid' => ['required', 'uuid:4'],
            'channel' => ['missing'],
            'recipient' => ['missing'],
            'delivery_status' => ['missing'],
        ];
    }
}
