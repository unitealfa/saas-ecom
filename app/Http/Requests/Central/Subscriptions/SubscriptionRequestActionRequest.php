<?php

namespace App\Http\Requests\Central\Subscriptions;

use App\Enums\Central\Subscriptions\PaymentChoiceEnum;
use App\Models\Central\Subscriptions\SubscriptionRequest;
use App\Models\Central\Users\User;
use App\Services\Central\Billing\PaymentDocumentService;
use App\Services\Central\Billing\SaasPaymentService;
use App\Services\Central\Subscriptions\SubscriptionRequestService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SubscriptionRequestActionRequest extends FormRequest
{
    private const array ACTIONS = ['prepareInvoice' => 'prepareInvoice', 'choice' => 'update', 'revise' => 'update', 'cancel' => 'cancel', 'proof' => 'update', 'accept' => 'accept', 'reject' => 'reject'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $request = $this->route('subscriptionRequest');
        $action = $this->route()?->getActionMethod();

        return $actor instanceof User && $request instanceof SubscriptionRequest && isset(self::ACTIONS[$action])
            && Gate::forUser($actor)->allows(self::ACTIONS[$action], $request);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $action = $this->route()?->getActionMethod();
        $request = $this->route('subscriptionRequest');
        $rules = ['expected_state' => ['required', 'string', 'regex:'.SubscriptionRequest::STATE_PATTERN]];
        if ($action === 'revise') {
            $rules = [...SubscriptionRequestService::rules(), ...$rules];
        } elseif ($action === 'choice') {
            $rules['payment_choice'] = ['required', 'integer', Rule::enum(PaymentChoiceEnum::class)];
        } elseif ($action === 'proof') {
            $rules = [...SaasPaymentService::declarationRules(false), ...$rules,
                'proof' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:'.PaymentDocumentService::MAX_KB]];
        } elseif ($action === 'reject') {
            $rules['reason'] = ['required', 'string', 'max:2000'];
            $rules['expected_payment_state'] = [$request instanceof SubscriptionRequest && $request->current_payment_id !== null ? 'required' : 'nullable', 'string', 'regex:'.SubscriptionRequest::STATE_PATTERN];
        } elseif ($action === 'accept') {
            $hasPayment = $request instanceof SubscriptionRequest && $request->current_payment_id !== null;
            $cash = $request instanceof SubscriptionRequest && $request->payment_choice === PaymentChoiceEnum::CASH;
            $rules = [...($cash && ! $hasPayment ? SaasPaymentService::declarationRules(true) : (! $cash ? SaasPaymentService::verificationRules() : ['received' => ['required', 'accepted']])), ...$rules];
            $rules['expected_payment_state'] = [$hasPayment ? 'required' : 'nullable', 'string', 'regex:'.SubscriptionRequest::STATE_PATTERN];
        }

        return [...$rules, ...array_fill_keys(['user_id', 'current_subscription_id', 'current_invoice_id', 'current_payment_id',
            'status', 'reference_version', 'decided_by_id', 'decided_at', 'decision_reason', 'invoice_error'], ['missing'])];
    }
}
