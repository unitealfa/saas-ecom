<?php

namespace App\Services\Central\Dashboard;

use App\Enums\Central\Deployments\StatusEnum as DeploymentStatus;
use App\Enums\Central\Tenants\StatusEnum;
use App\Models\Central\Deployments\TenantSchemaDeployment;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;
use Illuminate\Support\Collection;

class Dashboard
{
    public function __construct(private CentralUserAccess $access) {}

    /** @return array{shops: Collection<int, array{uuid: string, name: string, status: string, ready: bool}>, staff: bool} */
    public function forUser(User $user): array
    {
        $staff = $this->access->isStaff($user);

        if ($staff) {
            return ['shops' => collect(), 'staff' => true];
        }

        $shops = $user->tenants()->with('domains')->orderBy('id')->get();
        $pending = TenantSchemaDeployment::whereIn('tenant_id', $shops->modelKeys())
            ->whereIn('status', [DeploymentStatus::PENDING, DeploymentStatus::RUNNING])
            ->pluck('tenant_id')->map(fn ($id): int => (int) $id)->all();

        return ['shops' => $shops->map(function ($shop) use ($pending): array {
            $running = in_array($shop->id, $pending, true);
            $domain = $shop->domains->firstWhere('is_primary', true);
            $preparing = $running || ($shop->status === StatusEnum::ACTIVE
                && ($shop->provisioned_at === null || blank($shop->schema_version)));
            $ready = $shop->status === StatusEnum::ACTIVE && $shop->provisioned_at !== null
                && filled($shop->schema_version) && ! $running && $domain?->isReadyAsPrimary();

            return ['uuid' => $shop->uuid, 'name' => $shop->shop_name,
                'status' => $preparing ? 'En préparation' : $shop->status->label(), 'ready' => (bool) $ready];
        }), 'staff' => false];
    }
}
