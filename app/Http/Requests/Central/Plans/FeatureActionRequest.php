<?php

namespace App\Http\Requests\Central\Plans;

use App\Models\Central\Features\Feature;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class FeatureActionRequest extends FormRequest
{
    private const array ABILITIES = ['activate' => 'activate', 'archive' => 'archive'];

    public function authorize(): bool
    {
        $actor = $this->user('central');
        $feature = $this->route('feature');
        $ability = self::ABILITIES[$this->route()?->getActionMethod()] ?? null;

        return $actor instanceof User && $feature instanceof Feature && $ability !== null
            && Gate::forUser($actor)->allows($ability, $feature);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expected_state' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'is_active' => $this->route()?->getActionMethod() === 'activate' ? ['required', 'boolean'] : ['prohibited'],
            'code' => ['prohibited'], 'name' => ['prohibited'], 'value_type' => ['prohibited'],
            'quota_scope' => ['prohibited'], 'period' => ['prohibited'], 'unit' => ['prohibited'],
            'id' => ['prohibited'], 'uuid' => ['prohibited'], 'deleted_at' => ['prohibited'],
        ];
    }
}
