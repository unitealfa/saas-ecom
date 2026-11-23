<?php

namespace App\Console\Commands\Central;

use App\Enums\Central\Deployments\StatusEnum as DeploymentStatus;
use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Models\Central\Deployments\TenantSchemaDeployment;
use App\Models\Central\Tenants\Tenant;
use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;
use App\Services\Central\Tenants\TenantDeploymentService;
use App\Services\Central\Tenants\TenantSchemaVersions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ScheduleTenantMigrations extends Command
{
    protected $signature = 'aydra:schedule-tenant-migrations';

    protected $description = 'Mettre en file les migrations quotidiennes configurées.';

    public function handle(TenantDeploymentService $deployments, TenantSchemaVersions $versions, CentralUserAccess $access): int
    {
        if (tenancy()->initialized || ! Schema::hasTable('tenant_migration_schedules')
            || ! Schema::hasColumn('tenant_migration_schedules', 'start_date')) {
            return self::SUCCESS;
        }
        $schedule = DB::table('tenant_migration_schedules')->where('id', 1)->first();
        if (! $schedule || ! $schedule->enabled || ! $schedule->run_time || ! $schedule->timezone) {
            return self::SUCCESS;
        }
        $now = now($schedule->timezone);
        $date = $now->toDateString();
        if (($schedule->start_date !== null && $date < $schedule->start_date)
            || $now->format('H:i') !== $schedule->run_time || $schedule->last_run_on === $date) {
            return self::SUCCESS;
        }
        $actor = User::find($schedule->updated_by_id);
        if (! $actor instanceof User || ! $access->allows($actor, 'saas.deployments.migrate')) {
            Log::warning('Programmation des migrations ignorée : autorisation absente.');

            return self::FAILURE;
        }
        $target = $versions->latest();
        Tenant::whereNotNull('provisioned_at')->whereNotNull('schema_version')
            ->whereIn('status', [TenantStatus::ACTIVE->value, TenantStatus::INACTIVE->value, TenantStatus::SUSPENDED->value, TenantStatus::OVER_QUOTA->value])
            ->where('schema_version', '<', $target)->orderBy('id')->chunkById(100, function ($tenants) use ($schedule, $actor, $target, $deployments): void {
                foreach ($tenants as $tenant) {
                    if (! $tenant instanceof Tenant) {
                        continue;
                    }
                    $current = DB::table('tenant_migration_schedules')->where('id', 1)->first();
                    if (! $current || ! $current->enabled || $current->version !== $schedule->version) {
                        return;
                    }
                    $latest = TenantSchemaDeployment::where('tenant_id', $tenant->id)->latest('id')->first();
                    if (! $latest || in_array($latest->status, [DeploymentStatus::PENDING, DeploymentStatus::RUNNING, DeploymentStatus::FAILED], true)) {
                        continue;
                    }
                    try {
                        $deployments->migrate($actor, $tenant, $target, $latest->managementState());
                    } catch (Throwable $exception) {
                        Log::warning('Migration automatique non lancée.', ['tenant_uuid' => $tenant->uuid, 'exception_class' => $exception::class]);
                    }
                }
            });
        DB::table('tenant_migration_schedules')->where('id', 1)->where('version', $schedule->version)
            ->update(['last_run_on' => $date]);

        return self::SUCCESS;
    }
}
