<?php

namespace App\Services\Central\Diagnostics;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DatabaseDiagnostics
{
    private const string NOT_PROVISIONED = 'Non provisionnée';

    /** @return array<string, mixed> */
    public function read(): array
    {
        $db = DB::connection(config('tenancy.database.central_connection'));

        return [
            'database' => $db->getDatabaseName(),
            'tables' => $this->tableCounts($db),
            'tenants' => $this->tenants($db),
            'users' => $db->table('users')->orderBy('id')->get(['id', 'uuid', 'first_name', 'last_name', 'email']),
            'centralDomains' => config('tenancy.central_domains'),
        ];
    }

    /** @return array<string, int> */
    private function tableCounts(Connection $db): array
    {
        $tables = [];

        foreach ($db->getSchemaBuilder()->getTableListing($db->getDatabaseName(), false) as $table) {
            $tables[$table] = $db->table($table)->count();
        }

        ksort($tables);

        return $tables;
    }

    /** @return array<int, array<string, mixed>> */
    private function tenants(Connection $db): array
    {
        $domains = $db->table('domains')->whereNull('deleted_at')->orderBy('id')
            ->get(['id', 'tenant_id', 'domain'])->groupBy('tenant_id');

        $rows = $db->table('tenants as t')
            ->join('users as u', 'u.id', '=', 't.user_id')
            ->whereNull('t.deleted_at')
            ->orderBy('t.id')
            ->get(['t.id', 't.uuid', 't.user_id', 't.shop_name', 't.data', 'u.uuid as owner_uuid', 'u.first_name', 'u.last_name']);

        return $rows->map(fn (object $tenant): array => [
            'id' => $tenant->id,
            'uuid' => $tenant->uuid,
            'user_id' => $tenant->user_id,
            'owner_uuid' => $tenant->owner_uuid,
            'owner' => trim($tenant->first_name.' '.$tenant->last_name),
            'shop_name' => $tenant->shop_name,
            'database' => $this->databaseName($tenant->data),
            'domains' => $this->domainsOf($domains, $tenant->id),
        ])->all();
    }

    private function databaseName(?string $data): string
    {
        $decoded = $data === null ? [] : json_decode($data, true, flags: JSON_THROW_ON_ERROR);

        return $decoded['tenancy_db_name'] ?? self::NOT_PROVISIONED;
    }

    /**
     * @param  Collection<array-key, Collection<int, \stdClass>>  $domains
     * @return array<int, array{id: int, tenant_id: int, domain: string}>
     */
    private function domainsOf(Collection $domains, int $tenantId): array
    {
        return $domains->get($tenantId, collect())
            ->map(fn (\stdClass $domain): array => [
                'id' => $domain->id,
                'tenant_id' => $domain->tenant_id,
                'domain' => $domain->domain,
            ])
            ->all();
    }
}
