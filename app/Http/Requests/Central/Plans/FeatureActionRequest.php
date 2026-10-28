<?php

namespace App\Http\Requests\Central\Plans;

use App\Models\Central\Features\Feature;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class FeatureActionRequest extends FormRequest
{
    /** Actions du contrôleur gérées ici ; le nom de l'action est aussi celui de l'ability de la policy. */
    private const array ACTIONS = ['activate', 'archive'];

    private const array FORBIDDEN_FIELDS = [
        'code', 'name', 'value_type', 'unit', 'quota_scope', 'period', 'id', 'uuid', 'deleted_at',
    ];

    public function authorize(): bool
    {
        $actor = $this->user('central');
        $feature = $this->route('feature');
        $action = $this->route()?->getActionMethod();

        return $actor instanceof User
            && $feature instanceof Feature
            && in_array($action, self::ACTIONS, true)
            && Gate::forUser($actor)->allows($action, $feature);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expected_state' => ['required', 'string', 'regex:'.Feature::STATE_PATTERN],
            'is_active' => $this->route()?->getActionMethod() === 'activate' ? ['required', 'boolean'] : ['prohibited'],
            ...array_fill_keys(self::FORBIDDEN_FIELDS, ['prohibited']),
        ];
    }
}