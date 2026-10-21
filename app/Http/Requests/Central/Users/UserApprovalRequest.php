<?php

namespace App\Http\Requests\Central\Users;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use App\Enums\Central\Users\StatusEnum;
use Illuminate\Validation\Rule;

class UserApprovalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::forUser($this->user('central'))->allows(
            $this->approving() ? 'approve' : 'reject',
            $this->route('user'),
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'expected_status' => ['required', Rule::enum(StatusEnum::class)],
            'legal_profile_version' => ['required', 'integer', 'min:0'],
            'reason' => $this->approving() ? ['prohibited'] : ['required', 'string', 'max:1000'],
        ];
    }

    public function expectedStatus(): StatusEnum
    {
        return StatusEnum::from((int) $this->validated('expected_status'));
    }

    public function profileVersion(): int
    {
        return (int) $this->validated('legal_profile_version');
    }

    private function approving(): bool
    {
        return in_array($this->route()->getActionMethod(), ['approve', 'validateProfile'], true);
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Le motif du refus est obligatoire.',
            'expected_status.required' => 'Rechargez la fiche pour décider à partir de son état actuel.',
            'legal_profile_version.required' => 'Rechargez la fiche pour obtenir la version actuelle du dossier.',
        ];
    }
}
