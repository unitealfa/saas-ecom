<?php

namespace App\Http\Requests\Central\Users;

class StoreStaffRequest extends StoreUserRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge(['account_kind' => 'staff']);
    }
}
