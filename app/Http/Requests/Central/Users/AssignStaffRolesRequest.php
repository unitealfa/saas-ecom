<?php

namespace App\Http\Requests\Central\Users;

use Illuminate\Support\Facades\Gate;

class AssignStaffRolesRequest extends AssignRolesRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('central'))->allows('assignStaffRoles', $this->route('user'));
    }
}
