<?php

namespace App\Http\Requests\Central\Subscriptions;

use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Users\User;
use App\Services\Central\Subscriptions\SubscriptionTransitionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StartScheduledSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $subscription = $this->route('subscription');

        return $actor instanceof User && $subscription instanceof Subscription
            && Gate::forUser($actor)->allows('startNow', $subscription);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return SubscriptionTransitionService::startNowRules();
    }
}
