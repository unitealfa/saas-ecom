<?php

namespace App\Http\Requests\Central\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RenewStaffAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('central'))->allows('renewStaffAssignments', $this->route('user'));
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:1000'], 'expected_assigned_at' => ['required', 'date']];
    }
}
