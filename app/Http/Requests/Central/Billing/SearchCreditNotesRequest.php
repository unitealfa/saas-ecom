<?php

namespace App\Http\Requests\Central\Billing;

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Models\Central\Billing\SaasCreditNote;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SearchCreditNotesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user('central');

        return $actor instanceof User && Gate::forUser($actor)->allows($this->routeIs('owner.*', '*.owner.*') ? 'viewOwn' : 'viewAny', SaasCreditNote::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:255'], 'status' => ['nullable', 'integer', Rule::enum(DocumentStatusEnum::class)]];
    }
}
