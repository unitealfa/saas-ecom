<?php

namespace App\Http\Requests\Central\Geography;

use App\Models\Central\Geography\GeographicArea;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class GeographyActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $area = $this->route('area');

        return $actor instanceof User && $area instanceof GeographicArea
            && Gate::forUser($actor)->allows($this->isMethod('DELETE') ? 'archive' : 'activate', $area);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expected_state' => ['required', 'string', 'size:64'],
            'is_active' => [$this->isMethod('DELETE') ? 'prohibited' : 'required', 'boolean'],
        ];
    }
}
