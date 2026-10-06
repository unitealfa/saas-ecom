<?php

namespace App\Services\Central;

use App\Models\Central\Tenant;
use App\Models\Central\TenantStatus;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Throwable;

class TenantProvisioner
{
    public function provision(Tenant $tenant): void
    {
        $central = DB::connection(config('tenancy.database.central_connection'));

        if ($central->transactionLevel() > 0) {
            throw new LogicException('Tenant database DDL must run after the central transaction commits.');
        }

        if (! in_array($tenant->status, [TenantStatus::Provisioning, TenantStatus::ProvisioningFailed], true)) {
            throw new LogicException('This foundation provisioner only accepts unfinished tenants.');
        }

        $previous = tenancy()->tenant;

        try {
            $manager = $tenant->database()->manager();

            if (! $manager->databaseExists($tenant->database()->getName())) {
                CreateDatabase::dispatchSync($tenant);
            }

            $exitCode = Artisan::call('tenants:migrate', [
                '--tenants' => [$tenant->getTenantKey()],
                '--no-interaction' => true,
            ]);

            if ($exitCode !== 0) {
                throw new LogicException('Tenant migrations did not complete successfully.');
            }

            tenancy()->initialize($tenant);
            $owner = $tenant->owner;

            DB::connection('tenant')->transaction(function () use ($owner): void {
                if (! User::where('central_user_uuid', $owner->uuid)->exists()) {
                    $localOwner = new User([
                        'last_name' => $owner->last_name,
                        'first_name' => $owner->first_name,
                        'email' => $owner->email,
                        'password' => Str::random(64),
                        'locale' => $owner->locale,
                    ]);
                    $localOwner->central_user_uuid = $owner->uuid;
                    $localOwner->membership_status = 2;
                    $localOwner->save();
                }
            });

            $schemaVersion = DB::connection('tenant')->table('migrations')->orderByDesc('id')->value('migration');

            if (! is_string($schemaVersion)) {
                throw new LogicException('The tenant migration history is missing.');
            }

            $tenant->schema_version = $schemaVersion;
            $tenant->status = TenantStatus::Provisioning;
            $tenant->save();
        } catch (Throwable $exception) {
            $tenant->status = TenantStatus::ProvisioningFailed;
            $tenant->save();

            throw $exception;
        } finally {
            tenancy()->end();

            if ($previous !== null) {
                tenancy()->initialize($previous);
            }
        }
    }
}
