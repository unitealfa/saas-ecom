<?php

namespace App\Services\Central\Operations;

use App\Models\Central\Users\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OperationsReader
{
    /** @return array{deployments: \Illuminate\Contracts\Pagination\LengthAwarePaginator, deliveries: \Illuminate\Contracts\Pagination\LengthAwarePaginator} */
    public function overview(User $actor): array
    {
        Gate::forUser($actor)->authorize('viewOperations', User::class);
        $db = DB::connection(config('tenancy.database.central_connection'));

        return [
            'deployments' => $db->table('tenant_schema_deployments as deployments')
                ->join('tenants', 'tenants.id', '=', 'deployments.tenant_id')->orderByDesc('deployments.id')
                ->paginate(15, ['deployments.uuid', 'tenants.uuid as shop_uuid', 'deployments.operation', 'deployments.status',
                    'deployments.attempt_number', 'deployments.created_at', 'deployments.ended_at'], 'deployments_page')
                ->withQueryString(),
            'deliveries' => $db->table('saas_document_deliveries')->orderByDesc('id')
                ->paginate(15, ['uuid', 'channel', 'delivery_status', 'attempts_count', 'created_at', 'sent_at'], 'deliveries_page')
                ->withQueryString(),
        ];
    }
}
