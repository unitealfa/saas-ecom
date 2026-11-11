<?php

namespace App\Services\Central\Dashboard;

use App\Enums\Central\Billing\TransferStatusEnum;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Roles\Role;
use App\Models\Central\Users\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use stdClass;

class AdminDashboardService
{
    /** @return array{merchants: array<int, int>, shops: array<int, int>, subscriptions: array<int, int>} */
    public function overview(User $actor): array
    {
        Gate::forUser($actor)->authorize('viewAdminDashboard', User::class);

        $connection = DB::connection(config('tenancy.database.central_connection'));
        $roleAssignments = config('permission.table_names.model_has_roles');
        $roles = config('permission.table_names.roles');

        return [
            'merchants' => $connection->table('users')->whereNull('users.deleted_at')
                ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')
                    ->from($roleAssignments)->join($roles, "$roles.id", '=', "$roleAssignments.role_id")
                    ->whereColumn("$roleAssignments.model_id", 'users.id')
                    ->where("$roleAssignments.model_type", $actor->getMorphClass())
                    ->where("$roles.guard_name", Role::GUARD))
                ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->all(),
            'shops' => $connection->table('tenants')->whereNull('deleted_at')
                ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->all(),
            'subscriptions' => $connection->table('subscriptions')
                ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->all(),
        ];
    }

    /** @return list<array{currency: string, count: int, total: string}> */
    public function financial(User $actor): array
    {
        Gate::forUser($actor)->authorize('viewFinancialDashboard', User::class);

        return DB::connection(config('tenancy.database.central_connection'))->table('saas_transfers')
            ->where('record_type', SaasPayment::RECORD_TYPE)->where('transfer_status', TransferStatusEnum::VERIFIED->value)
            ->whereNull('reversal_of_id')->whereNotNull('validated_at')
            ->selectRaw('currency, COUNT(*) AS payment_count, SUM(amount) AS payment_total')
            ->groupBy('currency')->orderBy('currency')->get()
            ->map(fn (stdClass $payment): array => [
                'currency' => (string) $payment->currency,
                'count' => (int) $payment->payment_count,
                'total' => (string) $payment->payment_total,
            ])->all();
    }
}
