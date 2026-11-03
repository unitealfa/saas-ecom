<?php

namespace App\Http\Requests\Central\Subscriptions;

use App\Models\Central\Subscriptions\Subscription;
use App\Services\Central\Subscriptions\SubscriptionAssignmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AssignSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('central'))->allows('assign', Subscription::class);
    }

    public function rules(): array
    {
        return SubscriptionAssignmentService::rules();
    }

    protected function prepareForValidation(): void
    {
        $plan = $this->input('plan_uuid');
        $period = $this->input('period');
        $quotes = $this->input('quoted_prices');
        if (is_string($plan) && (is_string($period) || is_int($period)) && is_array($quotes)) {
            $prices = $quotes[$plan] ?? null;
            if (is_array($prices)) {
                $this->merge(['expected_amount' => $prices[$period] ?? null]);
            }
        }
    }
}
