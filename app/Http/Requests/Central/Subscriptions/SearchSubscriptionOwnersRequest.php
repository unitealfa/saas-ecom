<?php

namespace App\Http\Requests\Central\Subscriptions;

use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SearchSubscriptionOwnersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');

        return $actor instanceof User && Gate::forUser($actor)->allows('assign', Subscription::class)
            && Gate::forUser($actor)->allows('saas.payments.validate');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'owner' => ['nullable', 'string', 'uuid'],
            'ownersPage' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
