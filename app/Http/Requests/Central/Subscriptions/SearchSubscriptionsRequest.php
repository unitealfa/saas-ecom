<?php

namespace App\Http\Requests\Central\Subscriptions;

use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SearchSubscriptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');

        return $actor instanceof User && Gate::forUser($actor)->allows('viewAny', Subscription::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('search'))) {
            $this->merge(['search' => trim($this->input('search'))]);
        }
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'integer', Rule::enum(StatusEnum::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
