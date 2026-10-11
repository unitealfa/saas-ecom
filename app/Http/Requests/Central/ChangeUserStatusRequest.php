<?php

namespace App\Http\Requests\Central;

use App\Enums\Users\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ChangeUserStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $action = $this->input('status') == StatusEnum::ACTIVE->value ? 'restore' : 'suspend';

        return Gate::forUser($this->user('central'))->allows($action, $this->route('user'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'integer', Rule::in([StatusEnum::ACTIVE->value, StatusEnum::SUSPENDED->value])],
        ];
    }
}
