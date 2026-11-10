<?php

namespace App\Services\Central\Dashboard;

use App\Enums\Central\Billing\TransferStatusEnum;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Tenants\Tenant;
use App\Models\Central\Users\User;
use Illuminate\Support\Facades\Gate;

class AdminDashboardService
{
    /** @return array{merchants: array<int, int>, shops: array<int, int>, subscriptions: array<int, int>} */
    public function overview(User $actor): array
    {
        Gate::forUser($actor)->authorize('viewAdminDashboard', User::class);

        return [
            'merchants' => User::query()->whereDoesntHave('roles', fn ($query) => $query->where('roles.guard_name', 'central'))
                ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->all(),
            'shops' => Tenant::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->all(),
            'subscriptions' => Subscription::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status')->all(),
        ];
    }

    /** @return list<array{currency: string, count: int, total: string}> */
    public function financial(User $actor): array
    {
        Gate::forUser($actor)->authorize('viewFinancialDashboard', User::class);

        return array_values(SaasPayment::query()->where('transfer_status', TransferStatusEnum::VERIFIED)
            ->whereNull('reversal_of_id')->whereNotNull('validated_at')
            ->selectRaw('currency, COUNT(*) AS payment_count, SUM(amount) AS payment_total')
            ->groupBy('currency')->orderBy('currency')->get()
            ->map(fn (SaasPayment $payment): array => [
                'currency' => (string) $payment->getAttribute('currency'),
                'count' => (int) $payment->getAttribute('payment_count'),
                'total' => (string) $payment->getAttribute('payment_total'),
            ])->all());
    }
}
