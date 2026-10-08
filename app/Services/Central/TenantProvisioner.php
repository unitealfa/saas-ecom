<?php

namespace App\Services\Central;

use App\DTOs\Tenant\ShopSettings;
use App\Enums\Central\Tenants\StatusEnum;
use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Models\Central\Tenant;
use App\Models\Tenant\Shop;
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

        if (! in_array($tenant->status, [StatusEnum::PROVISIONING, StatusEnum::PROVISIONING_FAILED], true)) {
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

            DB::connection('tenant')->transaction(function () use ($owner, $tenant): void {
                $shop = Shop::query()->where('singleton', 1)->lockForUpdate()->first();

                if ($shop === null) {
                    $settings = ShopSettings::fromArray($tenant->getInternal('shop_settings') ?? []);
                    $shop = new Shop($settings->toArray());
                    $shop->tenant_uuid = $tenant->uuid;
                    $shop->singleton = 1;
                    $shop->central_profile_version = $tenant->profile_version;
                    $shop->shop_name = $tenant->shop_name;
                    $shop->locale = $owner->locale;
                    $shop->currency = 'DZD';
                    $shop->timezone = 'Africa/Algiers';
                    $shop->save();
                } elseif ($shop->tenant_uuid !== $tenant->uuid) {
                    throw new LogicException('The shop profile belongs to another tenant.');
                }

                if (! User::where('central_user_uuid', $owner->uuid)->exists()) {
                    $localOwner = new User([
                        'last_name' => $owner->last_name,
                        'first_name' => $owner->first_name,
                        'email' => $owner->email,
                        'password' => Str::random(64),
                        'locale' => $owner->locale,
                    ]);
                    $localOwner->central_user_uuid = $owner->uuid;
                    $localOwner->membership_status = MembershipStatusEnum::INVITED;
                    $localOwner->save();
                }
            }, attempts: 3);

            $schemaVersion = DB::connection('tenant')->table('migrations')->orderByDesc('id')->value('migration');

            if (! is_string($schemaVersion)) {
                throw new LogicException('The tenant migration history is missing.');
            }

            $tenant->schema_version = $schemaVersion;
            $tenant->status = StatusEnum::PROVISIONING;
            $tenant->setInternal('shop_settings', null);
            $tenant->save();
        } catch (Throwable $exception) {
            $tenant->status = StatusEnum::PROVISIONING_FAILED;
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
