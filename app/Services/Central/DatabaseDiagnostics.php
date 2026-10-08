<?php

namespace App\Services\Central;

use Illuminate\Support\Facades\DB;

class DatabaseDiagnostics
{
    /** @return array<string, mixed> */
    public function read(): array
    {
        $db = DB::connection(config('tenancy.database.central_connection'));
        $tables = [];
        $schema = $db->getDatabaseName();
        foreach ($db->getSchemaBuilder()->getTableListing($schema, false) as $table) {
            $tables[$table] = $db->table($table)->count();
        }
        ksort($tables);
        $domains = $db->table('domains')->whereNull('deleted_at')->orderBy('id')
            ->get(['id', 'tenant_id', 'domain'])->groupBy('tenant_id');
        $tenants = [];
        foreach ($db->table('tenants as t')->join('users as u', 'u.id', '=', 't.user_id')->whereNull('t.deleted_at')
            ->orderBy('t.id')->get(['t.id', 't.uuid', 't.user_id', 't.shop_name', 't.data', 'u.uuid as owner_uuid', 'u.first_name', 'u.last_name']) as $tenant) {
            $data = json_decode($tenant->data, true, flags: JSON_THROW_ON_ERROR);
            $tenantDomains = [];
            foreach ($domains->get($tenant->id, collect()) as $domain) {
                $tenantDomains[] = ['id' => $domain->id, 'tenant_id' => $domain->tenant_id, 'domain' => $domain->domain];
            }
            $tenants[] = [
                'id' => $tenant->id, 'uuid' => $tenant->uuid, 'user_id' => $tenant->user_id,
                'owner_uuid' => $tenant->owner_uuid, 'owner' => trim($tenant->first_name.' '.$tenant->last_name),
                'shop_name' => $tenant->shop_name, 'database' => $data['tenancy_db_name'] ?? 'Non provisionnée', 'domains' => $tenantDomains,
            ];
        }

        return [
            'database' => $db->getDatabaseName(), 'tables' => $tables, 'tenants' => $tenants,
            'users' => $db->table('users')->orderBy('id')->get(['id', 'uuid', 'first_name', 'last_name', 'email']),
            'centralDomains' => config('tenancy.central_domains'),
        ];
    }
}
