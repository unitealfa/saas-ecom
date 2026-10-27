<?php

namespace App\Http\Requests\Central\Tenants;

use App\Models\Central\Tenants\Tenant;
use Illuminate\Support\Facades\Gate;

class StoreOwnedTenantRequest extends StoreTenantRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('central'))->allows('createOwned', [Tenant::class, $this->user('central')]);
    }
}
