<?php

namespace App\Services\Tenant\Diagnostics;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

class DatabaseDiagnostics
{
    private const string CONNECTION = 'tenant';

    private const int RECENT_ORDERS = 20;

    public function read(): array
    {
        if (! tenancy()->initialized) {
            throw new LogicException('Tenant diagnostics require tenant initialization.');
        }

        $db = DB::connection(self::CONNECTION);
        $recentOrders = $this->recentOrdersQuery($db);
        $orders = collect();

        $measures = [
            $this->measure($db, 'Liste des 20 dernières commandes avec jointure', function () use ($recentOrders, &$orders): array {
                $orders = $recentOrders->get();

                return $orders->all();
            }),
            $this->measure($db, 'Même liste avec une requête par commande (comparaison N+1)', fn (): array => $this->oneQueryPerOrder($db)),
            $this->measure($db, 'Recherche d’une commande par UUID indexé', fn (): array => $this->findOrderByUuid($db)),
        ];

        return [
            'tenantId' => tenant('id'),
            'tenantUuid' => tenant('uuid'),
            'database' => $db->getDatabaseName(),
            'shopName' => $db->table('shop')->value('shop_name'),
            'tables' => $this->tableCounts($db),
            'users' => $db->table('users')->orderBy('id')->get(['id', 'uuid', 'central_user_uuid', 'first_name', 'last_name', 'email', 'membership_status']),
            'orders' => $orders,
            'measures' => $measures,
            'plan' => in_array($db->getDriverName(), ['mysql', 'mariadb'], true) ? $recentOrders->explain()->all() : [],
        ];
    }

    private function tableCounts(Connection $db): array
    {
        $tables = [];

        foreach ($db->getSchemaBuilder()->getTableListing($db->getDatabaseName(), false) as $table) {
            $tables[$table] = $db->table($table)->count();
        }

        ksort($tables);

        return $tables;
    }

    private function recentOrdersQuery(Connection $db): Builder
    {
        return $db->table('orders as o')
            ->join('order_revisions as r', 'r.id', '=', 'o.current_revision_id')
            ->orderByDesc('o.id')
            ->limit(self::RECENT_ORDERS)
            ->select(['o.id', 'o.uuid', 'o.number', 'o.commercial_status', 'r.order_total', 'r.currency']);
    }

    private function oneQueryPerOrder(Connection $db): array
    {
        $rows = [];

        foreach ($db->table('orders')->orderByDesc('id')->limit(self::RECENT_ORDERS)->get(['current_revision_id']) as $order) {
            $rows[] = $db->table('order_revisions')->where('id', $order->current_revision_id)->first(['order_total']);
        }

        return $rows;
    }

    private function findOrderByUuid(Connection $db): array
    {
        $uuid = $db->table('orders')->orderBy('id')->value('uuid');

        return $db->table('orders')->where('uuid', $uuid)->get(['id', 'uuid', 'number'])->all();
    }

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
                'name' => $name,
                'queries' => count($queries),
                'rows' => count($rows),
                'sql_ms' => round(array_sum(array_column($queries, 'time')), 3),
                'elapsed_ms' => round($elapsed, 3),
            ];
        } finally {
            if (! $wasLogging) {
                $db->disableQueryLog();
                $db->flushQueryLog();
            }
        }
    }
}