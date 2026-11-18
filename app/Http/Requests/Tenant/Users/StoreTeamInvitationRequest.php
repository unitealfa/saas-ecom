<?php

namespace App\Http\Requests\Tenant\Users;

use App\Models\Tenant\Users\TeamInvitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class StoreTeamInvitationRequest extends FormRequest
{
    public function authorize(): bool { return Gate::forUser($this->user('tenant'))->allows('create', TeamInvitation::class); }
    public function rules(): array
    {
        return ['email' => ['required', 'email:rfc', 'max:255'], 'role' => ['required', 'uuid']];
    }
}
