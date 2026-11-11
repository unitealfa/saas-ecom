<?php

namespace App\Http\Requests\Central\Operations;

use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReconcileOperationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $ability = match ($this->route()?->getActionMethod()) {
            'reconcileSubscriptions' => 'reconcileSubscriptions',
            'reconcilePayments' => 'reconcilePayments',
            default => null,
        };

        return $actor instanceof User && $ability !== null && Gate::forUser($actor)->allows($ability, User::class);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['cursor' => ['nullable', 'uuid']];
    }
}
