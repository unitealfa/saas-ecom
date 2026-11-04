<?php

namespace App\Http\Requests\Central\Subscriptions;

use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Users\User;
use App\Services\Central\Subscriptions\SubscriptionTransitionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DeactivateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subscription = $this->route('subscription');
        $actor = $this->user('central');

        return $actor instanceof User && $subscription instanceof Subscription && Gate::forUser($actor)->allows('deactivate', $subscription);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return SubscriptionTransitionService::closeRules();
    }
}
