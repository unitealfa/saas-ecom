<?php

namespace App\Http\Requests\Central\Audit;

use Illuminate\Foundation\Http\FormRequest;

class SearchActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('central') !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'log_name' => ['nullable', 'string', 'max:255'],
            'event' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => array_merge(['nullable', 'date_format:Y-m-d'], $this->filled('from') ? ['after_or_equal:from'] : []),
            'actor_uuid' => ['nullable', 'string', 'uuid'],
            'subject_uuid' => ['nullable', 'string', 'uuid'],
            'correlation_id' => ['nullable', 'string', 'uuid'],
        ];
    }
}
