<?php

namespace App\Http\Requests\Central\Billing;

use App\Enums\Central\Billing\DeliveryStatusEnum;
use App\Models\Central\Billing\SaasDocumentDelivery;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SearchSaasDeliveriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');

        return $actor instanceof User && Gate::forUser($actor)->allows('viewAny', SaasDocumentDelivery::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'document_uuid' => ['nullable', 'uuid:4'],
            'status' => ['nullable', 'integer', Rule::enum(DeliveryStatusEnum::class)],
        ];
    }
}
