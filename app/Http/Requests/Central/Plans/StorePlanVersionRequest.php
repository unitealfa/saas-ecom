<?php

namespace App\Http\Requests\Central\Plans;

use App\Http\Controllers\Central\Admin\Plans\PlanPageController;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Users\User;
use App\Services\Central\Plans\PlanVersionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StorePlanVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plan = $this->route('plan');
        $actor = $this->user('central');

        return $actor instanceof User && $plan instanceof Plan && Gate::forUser($actor)->allows('createVersion', $plan);
    }

    public function rules(): array
    {
        $rules = PlanVersionService::rules();
        if ($this->route()?->getControllerClass() !== PlanPageController::class) {
            return $rules;
        }

        return [...$rules,
            'draft' => ['required', 'array:preview,information,edit_features,selected,enabled,limits,confirmed_copy'],
            'draft.preview' => ['required', 'accepted'],
            'draft.information' => ['sometimes', 'array:name,description,monthly_price,annual_price'],
            'draft.edit_features' => ['sometimes', 'accepted'],
            'draft.selected' => ['sometimes', 'array', 'list'],
            'draft.selected.*' => ['required', 'uuid:4', 'distinct'],
            'draft.enabled' => ['sometimes', 'array'],
            'draft.limits' => ['sometimes', 'array'],
            'draft.confirmed_copy' => ['sometimes', 'accepted'],
        ];
    }

    /** @return array{information?: array<string, mixed>, features?: list<array<string, mixed>>, confirmed_copy?: bool} */
    public function draft(): array
    {
        $input = $this->validated('draft');
        $draft = ['confirmed_copy' => isset($input['confirmed_copy'])];
        if (isset($input['information'])) {
            $draft['information'] = $input['information'];
        }
        if (isset($input['edit_features'])) {
            $draft['features'] = [];
            foreach ($input['selected'] ?? [] as $uuid) {
                $draft['features'][] = ['uuid' => $uuid, 'is_active' => isset($input['enabled'][$uuid]),
                    'limit' => $input['limits'][$uuid] ?? null];
            }
        }

        return $draft;
    }
}
