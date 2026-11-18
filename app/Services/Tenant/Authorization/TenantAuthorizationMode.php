<?php

namespace App\Services\Tenant\Authorization;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Transition scoped to the current tenant; deliberately never cached globally. */
final class TenantAuthorizationMode
{
    public static function individualOnly(): bool
    {
        if (! tenancy()->initialized || ! Schema::connection('tenant')->hasTable('tenant_authorization_state')) {
            return false;
        }

        return DB::connection('tenant')->table('tenant_authorization_state')
            ->where('id', 1)->value('individual_only') == 1;
    }
}
