<?php

namespace App\Http\Requests\Central\Subscriptions;

use App\Enums\Central\Subscriptions\RequestStatusEnum;
use App\Models\Central\Subscriptions\SubscriptionRequest;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SearchSubscriptionRequestsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user('central');

        return $actor instanceof User && Gate::forUser($actor)->allows($this->routeIs('owner.*', '*.owner.*') ? 'create' : 'viewAny', SubscriptionRequest::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'integer', Rule::enum(RequestStatusEnum::class)]];
    }
}
