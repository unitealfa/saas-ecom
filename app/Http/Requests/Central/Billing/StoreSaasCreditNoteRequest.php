<?php

namespace App\Http\Requests\Central\Billing;

use App\Models\Central\Billing\SaasCreditNote;
use App\Models\Central\Users\User;
use App\Services\Central\Billing\SaasCreditNoteService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreSaasCreditNoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $credit = $this->route('credit');

        return $actor instanceof User && Gate::forUser($actor)->allows($credit instanceof SaasCreditNote ? 'update' : 'create', $credit ?? SaasCreditNote::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return SaasCreditNoteService::rules(! ($this->route('credit') instanceof SaasCreditNote));
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');
        if (is_array($lines)) {
            $this->merge(['lines' => array_filter($lines, fn ($line): bool => ! is_array($line)
                || filled($line['quantity'] ?? null) || filled($line['total_amount'] ?? null))]);
        }
    }
}
