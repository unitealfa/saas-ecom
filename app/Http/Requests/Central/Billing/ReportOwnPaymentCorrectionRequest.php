<?php

namespace App\Http\Requests\Central\Billing;

use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Users\User;
use App\Services\Central\Billing\OwnerPaymentCorrectionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ReportOwnPaymentCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $payment = $this->route('payment');

        return $actor instanceof User
            && $payment instanceof SaasPayment
            && Gate::forUser($actor)->allows('viewOwn', SaasPayment::class)
            && Gate::forUser($actor)->allows('view', $payment);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...OwnerPaymentCorrectionService::rules(),
            'amount' => ['missing'],
            'transfer_status' => ['missing'],
            'validated_at' => ['missing'],
            'user_id' => ['missing'],
            'document_id' => ['missing'],
        ];
    }
}
