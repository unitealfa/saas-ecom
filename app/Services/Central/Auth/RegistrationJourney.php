<?php

namespace App\Services\Central\Auth;

use App\Enums\Central\Users\StatusEnum;
use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Models\Central\Activity;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Services\Central\Authorization\CentralUserAccess;

class RegistrationJourney
{
    public function __construct(private CentralUserAccess $access) {}

    public function mayAuthenticate(User $user): bool
    {
        return $user->status === StatusEnum::ACTIVE
            || (in_array($user->status, [StatusEnum::PENDING, StatusEnum::REJECTED], true) && ! $this->access->isStaff($user));
    }

    public function destination(User $user): string
    {
        return $this->progress($user)['redirect'];
    }

    /** @return array{state: string, redirect: string, reason: ?string, shops: array, version: string} */
    public function progress(User $user): array
    {
        $shops = [];
        if (! $user->hasVerifiedEmail()) {
            $state = 'verification';
        } elseif ($user->status === StatusEnum::PENDING) {
            $state = 'pending';
        } elseif ($user->status === StatusEnum::REJECTED) {
            $state = 'rejected';
        } elseif ($this->access->isStaff($user)) {
            $state = 'ready';
        } else {
            $tenants = Tenant::where('user_id', $user->id)->orderBy('id')
                ->get(['shop_name', 'status', 'schema_version', 'provisioned_at']);
            $shops = $tenants->map(function (Tenant $tenant): array {
                $prepared = $tenant->provisioned_at !== null && filled($tenant->schema_version);

                return [
                    'name' => $tenant->shop_name,
                    'status' => $tenant->status->label(),
                    'prepared' => $prepared,
                    'failed' => $tenant->status === TenantStatus::PROVISIONING_FAILED,
                    'ready' => $prepared && $tenant->status === TenantStatus::ACTIVE,
                ];
            })->all();
            $state = $tenants->isEmpty() ? 'setup' : (array_any($shops, fn (array $shop): bool => $shop['ready']) ? 'ready' : 'preparing');
        }

        $reason = $state === 'rejected' ? $this->refusalReason($user) : null;

        return [
            'state' => $state,
            'redirect' => match ($state) {
                'verification' => route('verification.notice'),
                'setup' => route('owner.first-shop'),
                'ready' => route('dashboard'),
                default => route('registration.status'),
            },
            'reason' => $reason,
            'shops' => $shops,
            'version' => hash('sha256', json_encode([$state, $reason, $shops], JSON_THROW_ON_ERROR)),
        ];
    }

    private function refusalReason(User $user): ?string
    {
        $decision = Activity::forSubject($user)
            ->whereIn('event', ['central_account_approved', 'central_account_rejected'])
            ->orderByDesc('id')->first(['event', 'properties']);
        $reason = $decision?->event === 'central_account_rejected' ? $decision->getProperty('reason') : null;

        return is_string($reason) && trim($reason) !== '' ? $reason : null;
    }
}
