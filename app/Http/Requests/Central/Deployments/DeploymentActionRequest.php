<?php

namespace App\Http\Requests\Central\Deployments;

use App\Models\Central\TenantSchemaDeployment;
use App\Models\Central\User;
use App\Services\Central\Tenants\TenantSchemaVersions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DeploymentActionRequest extends FormRequest
{
    private const array ABILITIES = ['provision' => 'provision', 'migrate' => 'migrate', 'retry' => 'retry'];

    public function authorize(): bool
    {
        $actor = $this->user('central');
        $deployment = $this->route('deployment');
        $ability = self::ABILITIES[$this->route()?->getActionMethod()] ?? null;
        if (! $actor instanceof User || ! $deployment instanceof TenantSchemaDeployment || $ability === null) {
            return false;
        }

        return $ability === 'migrate'
            ? $deployment->tenant !== null && Gate::forUser($actor)->allows($ability, [TenantSchemaDeployment::class, $deployment->tenant])
            : Gate::forUser($actor)->allows($ability, $deployment);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expected_state' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'target_version' => $this->route()?->getActionMethod() === 'migrate'
                ? ['required', 'string', Rule::in([app(TenantSchemaVersions::class)->latest()])]
                : ['prohibited'],
            'tenant_id' => ['prohibited'], 'status' => ['prohibited'], 'operation' => ['prohibited'],
            'operation_key' => ['prohibited'], 'path' => ['prohibited'], 'command' => ['prohibited'],
        ];
    }
}
