<?php

namespace App\Http\Requests\Central\Domains;

use App\Models\Central\Domain;
use App\Models\Central\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DomainActionRequest extends FormRequest
{
    /** Méthode du contrôleur => ability de la policy. */
    private const array ABILITIES = [
        'verify' => 'verify',
        'primary' => 'setPrimary',
        'destroy' => 'archive',
        'restore' => 'restore',
    ];

    public function authorize(): bool
    {
        $actor = $this->user('central');
        $domain = $this->route('domain');
        $ability = self::ABILITIES[$this->action()] ?? null;

        return $actor instanceof User
            && $domain instanceof Domain
            && $ability !== null
            && Gate::forUser($actor)->allows($ability, $domain);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expected_state' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'expected_primary_uuid' => $this->action() === 'primary'
                ? ['present', 'nullable', 'uuid']
                : ['prohibited'],
        ];
    }

    private function action(): string
    {
        return (string) $this->route()?->getActionMethod();
    }
}
