<?php

namespace App\Services\Central\Tenants;

use App\DTOs\Tenant\Shop\ShopSettings;
use App\Enums\Central\Tenants\StatusEnum;
use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Models\Central\Tenant;
use App\Models\Central\User as CentralUser;
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
    private const string TENANT_CONNECTION = 'tenant';

    private const int SINGLETON = 1;

    private const int ATTEMPTS = 3;

    private const string DEFAULT_CURRENCY = 'DZD';

    private const string DEFAULT_TIMEZONE = 'Africa/Algiers';

    public function __construct(private TenantSchemaVersions $versions) {}

    public function provision(Tenant $tenant, ?string $targetVersion = null): void
    {
        $this->assertCanRun($tenant);

        $previous = tenancy()->tenant;

        try {
            $this->createDatabase($tenant);
            $this->migrate($tenant, $targetVersion);

            tenancy()->initialize($tenant);
            $this->assertVersion($targetVersion ?? $this->versions->latest());

            $owner = $tenant->owner
                ?? throw new LogicException('The tenant owner is missing.');

            DB::transaction(function () use ($owner, $tenant): void {
                $this->ensureShop($tenant, $owner);
                $this->ensureLocalOwner($owner);
            }, attempts: self::ATTEMPTS);

            $this->markPrepared($tenant);
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

    private function assertCanRun(Tenant $tenant): void
    {
        $central = DB::connection(config('tenancy.database.central_connection'));

        if ($central->transactionLevel() > 0) {
            throw new LogicException('Tenant database DDL must run after the central transaction commits.');
        }

        if (! in_array($tenant->status, [StatusEnum::PROVISIONING, StatusEnum::PROVISIONING_FAILED], true)) {
            throw new LogicException('This foundation provisioner only accepts unfinished tenants.');
        }
    }

    private function createDatabase(Tenant $tenant): void
    {
        $database = $tenant->database();

        if (! $database->manager()->databaseExists($database->getName())) {
            CreateDatabase::dispatchSync($tenant);
        }
    }

    public function migrateExisting(Tenant $tenant, string $targetVersion): void
    {
        if (tenancy()->initialized || DB::transactionLevel() > 0) {
            throw new LogicException('Tenant migrations require the committed central context.');
        }
        if (! $tenant->database()->manager()->databaseExists($tenant->database()->getName())) {
            throw new LogicException('The tenant database is missing.');
        }
        try {
            tenancy()->initialize($tenant);
            if (Shop::where('singleton', self::SINGLETON)->value('tenant_uuid') !== $tenant->uuid) {
                throw new LogicException('The shop profile belongs to another tenant.');
            }
            tenancy()->end();
            $this->migrate($tenant, $targetVersion);
            tenancy()->initialize($tenant);
            $this->assertVersion($targetVersion);
            $tenant->schema_version = $targetVersion;
            $tenant->save();
        } finally {
            tenancy()->end();
        }
    }

    private function migrate(Tenant $tenant, ?string $targetVersion = null): void
    {
        $exitCode = Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->getTenantKey()],
            '--path' => $this->versions->through($targetVersion ?? $this->versions->latest()),
            '--realpath' => true,
            '--force' => true,
            '--no-interaction' => true,
        ]);

        if ($exitCode !== 0) {
            throw new LogicException('Tenant migrations did not complete successfully.');
        }
    }

    private function assertVersion(string $targetVersion): void
    {
        $expected = array_map(fn (string $file): string => pathinfo($file, PATHINFO_FILENAME),
            $this->versions->through($targetVersion));
        $applied = DB::connection(self::TENANT_CONNECTION)->table('migrations')->pluck('migration')->all();
        if (array_diff($expected, $applied) !== []) {
            throw new LogicException('Tenant migrations did not reach the requested version.');
        }
    }

    private function ensureShop(Tenant $tenant, CentralUser $owner): void
    {
        $shop = Shop::query()->where('singleton', self::SINGLETON)->lockForUpdate()->first();

        if ($shop !== null) {
            if ($shop->tenant_uuid !== $tenant->uuid) {
                throw new LogicException('The shop profile belongs to another tenant.');
            }

            return;
        }

        $settings = ShopSettings::fromArray($tenant->getInternal('shop_settings') ?? []);

        $shop = new Shop($settings->toArray());
        $shop->tenant_uuid = $tenant->uuid;
        $shop->singleton = self::SINGLETON;
        $shop->central_profile_version = $tenant->profile_version;
        $shop->shop_name = $tenant->shop_name;
        $shop->locale = $owner->locale;
        $shop->currency = self::DEFAULT_CURRENCY;
        $shop->timezone = self::DEFAULT_TIMEZONE;
        $shop->save();
    }

    private function ensureLocalOwner(CentralUser $owner): void
    {
        if (User::where('central_user_uuid', $owner->uuid)->exists()) {
            return;
        }

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

    private function markPrepared(Tenant $tenant): void
    {
        $schemaVersion = DB::connection(self::TENANT_CONNECTION)->table('migrations')->orderByDesc('id')->value('migration');

        if (! is_string($schemaVersion)) {
            throw new LogicException('The tenant migration history is missing.');
        }

        $tenant->schema_version = $schemaVersion;
        $tenant->provisioned_at ??= now();
        $tenant->status = StatusEnum::PROVISIONING;
        $tenant->setInternal('shop_settings', null);
        $tenant->save();
    }
}
