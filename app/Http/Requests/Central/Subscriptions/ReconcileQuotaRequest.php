<?php

namespace App\Http\Requests\Central\Subscriptions;

use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReconcileQuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $owner = $this->route('user');

        return $actor instanceof User && $owner instanceof User && Gate::forUser($actor)->allows('reconcileQuota', $owner);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['expected_state' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']];
    }
}
