<?php

namespace App\Http\Requests\Central\Billing;

use App\Enums\Central\Billing\PolicyStatusEnum;
use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SearchBillingRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');

        return $actor instanceof User && Gate::forUser($actor)->allows('viewAny', BillingRule::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'policy_status' => ['nullable', Rule::enum(PolicyStatusEnum::class)],
            'version' => ['nullable', 'integer', 'min:1'],
            'period_start' => ['nullable', 'required_with:period_end', 'date'],
            'period_end' => ['nullable', 'required_with:period_start', 'date', 'after:period_start'],
        ];
    }
}
