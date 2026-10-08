<?php

namespace App\Services\Tenant;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use LogicException;

class DatabaseDiagnostics
{
    /** @return array<string, mixed> */
    public function read(): array
    {
        if (! tenancy()->initialized) {
            throw new LogicException('Tenant diagnostics require tenant initialization.');
        }

        $db = DB::connection('tenant');
        $tables = [];
        $schema = $db->getDatabaseName();
        foreach ($db->getSchemaBuilder()->getTableListing($schema, false) as $table) {
            $tables[$table] = $db->table($table)->count();
        }
        ksort($tables);
        $query = $db->table('orders as o')->join('order_revisions as r', 'r.id', '=', 'o.current_revision_id')
            ->orderByDesc('o.id')->limit(20)->select(['o.id', 'o.uuid', 'o.number', 'o.commercial_status', 'r.order_total', 'r.currency']);
        $measures = [];
        $measures[] = $this->measure($db, 'Liste des 20 dernières commandes avec jointure', fn (): array => $query->get()->all());
        $measures[] = $this->measure($db, 'Même liste avec une requête par commande (comparaison N+1)', function () use ($db): array {
            $rows = [];
            foreach ($db->table('orders')->orderByDesc('id')->limit(20)->get(['current_revision_id']) as $order) {
                $rows[] = $db->table('order_revisions')->where('id', $order->current_revision_id)->first(['order_total']);
            }

            return $rows;
        });
        $measures[] = $this->measure($db, 'Recherche d’une commande par UUID indexé', function () use ($db): array {
            $uuid = $db->table('orders')->orderBy('id')->value('uuid');

            return $db->table('orders')->where('uuid', $uuid)->get(['id', 'uuid', 'number'])->all();
        });

        return [
            'tenantId' => tenant('id'), 'tenantUuid' => tenant('uuid'), 'database' => $db->getDatabaseName(),
            'shopName' => $db->table('shop')->value('shop_name'), 'tables' => $tables,
            'users' => $db->table('users')->orderBy('id')->get(['id', 'uuid', 'central_user_uuid', 'first_name', 'last_name', 'email', 'membership_status']),
            'orders' => $query->get(), 'measures' => $measures,
            'plan' => $db->getDriverName() === 'mysql' ? $query->explain()->all() : [],
        ];
    }

    /**
     * @param  Closure(): array<mixed>  $operation
     * @return array{name: string, queries: int, rows: int, sql_ms: float, elapsed_ms: float}
     */
    private function measure(Connection $db, string $name, Closure $operation): array
    {
        $wasLogging = $db->logging();
        $offset = count($db->getQueryLog());
        $db->enableQueryLog();
        $started = hrtime(true);
        try {
            $rows = $operation();
            $elapsed = (hrtime(true) - $started) / 1_000_000;
            $queries = array_slice($db->getQueryLog(), $offset);

            return [
                'name' => $name, 'queries' => count($queries), 'rows' => count($rows),
                'sql_ms' => round(array_sum(array_column($queries, 'time')), 3), 'elapsed_ms' => round($elapsed, 3),
            ];
        } finally {
            if (! $wasLogging) {
                $db->disableQueryLog();
                $db->flushQueryLog();
            }
        }
    }
}
