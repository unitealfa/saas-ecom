<?php

namespace App\Http\Requests\Central\Users;

use App\Enums\Central\Users\StatusEnum;
use Illuminate\Support\Facades\Gate;

class ChangeStaffStatusRequest extends ChangeUserStatusRequest
{
    public function authorize(): bool
    {
        $ability = $this->input('status') == StatusEnum::ACTIVE->value ? 'activateStaff' : 'suspendStaff';

        return Gate::forUser($this->user('central'))->allows($ability, $this->route('user'));
    }
}
