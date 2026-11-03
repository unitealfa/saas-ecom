<?php

namespace App\Http\Requests\Central\Subscriptions;

use App\Models\Central\Subscriptions\Subscription;
use App\Services\Central\Subscriptions\SubscriptionTransitionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ChangeSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subscription = $this->route('subscription');

        return $subscription instanceof Subscription && Gate::forUser($this->user('central'))->allows('change', $subscription);
    }

    protected function prepareForValidation(): void
    {
        $plan = $this->input('plan_uuid');
        $period = $this->input('period');
        $quotes = $this->input('quoted_prices');
        if (is_string($plan) && (is_string($period) || is_int($period)) && is_array($quotes) && is_array($quotes[$plan] ?? null)) {
            $this->merge(['expected_amount' => $quotes[$plan][$period] ?? null]);
        }
    }

    public function rules(): array
    {
        return SubscriptionTransitionService::changeRules();
    }
}
