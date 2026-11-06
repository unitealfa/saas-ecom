<?php

namespace App\Http\Requests\Central\Billing;

use App\Enums\Central\Billing\TransferStatusEnum;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Users\User;
use App\Services\Central\Billing\PaymentDocumentService;
use App\Services\Central\Billing\SaasPaymentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PaymentActionRequest extends FormRequest
{
    private const array ACTIONS = ['verify' => 'verify', 'reject' => 'reject', 'reconcile' => 'reconcile', 'resumeCash' => 'verify', 'resumeProof' => 'supplyProof'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $payment = $this->route('payment');
        $action = $this->route()?->getActionMethod();

        return $actor instanceof User && $payment instanceof SaasPayment && isset(self::ACTIONS[$action])
            && Gate::forUser($actor)->allows(self::ACTIONS[$action], $payment);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $action = $this->route()?->getActionMethod();
        $rules = SaasPaymentService::verificationRules();
        if ($action === 'resumeCash') {
            $rules = ['expected_state' => $rules['expected_state'], 'received' => $rules['received']];
        } elseif ($action === 'resumeProof') {
            $rules = ['expected_state' => $rules['expected_state'], 'proof' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:'.PaymentDocumentService::MAX_KB]];
        } elseif ($action === 'reject') {
            $rules = ['expected_state' => $rules['expected_state'], 'reason' => ['required', 'string', 'max:2000']];
        } elseif ($action === 'reconcile') {
            unset($rules['received']);
            $payment = $this->route('payment');
            if ($payment instanceof SaasPayment && $payment->getAttribute('transfer_status') === TransferStatusEnum::UNCERTAIN) {
                $rules = ['expected_state' => $rules['expected_state'], 'review_note' => ['required', 'string', 'max:2000']];
            }
        }

        return [...$rules, ...array_fill_keys(['amount', 'currency', 'user_id', 'document_id', 'transfer_status', 'validated_by_id', 'validated_at',
            'operation_key', 'record_type', 'proof_media_id', 'source_proof_media_id', 'original_payment_id', 'reversal_of_id'], ['missing'])];
    }
}
