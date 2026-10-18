<?php

namespace App\Http\Requests\Central\Tenants;

use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Gate;

class StoreFirstTenantRequest extends StoreTenantRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user('central'))->allows('createFirst', [Tenant::class, $this->user('central')]);
    }
}
