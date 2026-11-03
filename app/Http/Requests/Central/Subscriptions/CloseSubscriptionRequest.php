<?php

namespace App\Http\Requests\Central\Subscriptions;

use App\Models\Central\Subscriptions\Subscription;
use App\Services\Central\Subscriptions\SubscriptionTransitionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CloseSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subscription = $this->route('subscription');

        return $subscription instanceof Subscription && Gate::forUser($this->user('central'))->allows('close', $subscription);
    }

    public function rules(): array
    {
        return SubscriptionTransitionService::closeRules();
    }
}
