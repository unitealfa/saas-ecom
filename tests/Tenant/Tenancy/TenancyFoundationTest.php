<?php

use App\Models\Central\Country;
use App\Models\Central\TenantStatus;
use App\Models\Tenant;
use App\Models\Tenant\User as ShopUser;
use App\Models\User;
use App\Services\Central\TenantProvisioner;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\TenancyBootstrapped;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Tests\Tenant\Tenancy\TenantTestSeeder;

beforeEach(function (): void {
    config(['tenancy.database.suffix' => '.sqlite']);
    config(['tenancy.saas_base_domain' => 'aydra.localhost']);
});

afterEach(function (): void {
    tenancy()->end();

    if (Schema::hasTable('tenants')) {
        foreach (Tenant::withTrashed()->get() as $tenant) {
            $name = $tenant->database()->getName();

            if (config('tenancy.database.central_connection') !== 'sqlite' || ! preg_match('/^boutique_[a-z0-9-]+\.sqlite$/D', $name)) {
                throw new LogicException('Refusing to remove a database outside this SQLite test.');
            }

            File::delete(database_path($name));
        }
    }
});

test('tenant database names use the initial slug without the numeric id within the MySQL limit', function (): void {
    config(['tenancy.database.suffix' => '']);
    $tenant = Tenant::factory()->make(['id' => 17, 'user_id' => 1, 'slug' => 'nour']);

    expect($tenant->database()->getName())->toBe('boutique_nour');

    $tenant->slug = str_repeat('a', 55);
    $name = $tenant->database()->getName();
    expect(strlen($name))->toBe(64);
    expect($name)->toBe('boutique_'.str_repeat('a', 55));

    $tenant->id = 18;
    expect($tenant->database()->getName())->toBe($name);

    $tenant->setInternal('db_name', 'boutique1');
    expect($tenant->database()->getName())->toBe('boutique1');
});

test('database naming rejects an invalid or overlong slug without truncation', function (string $slug): void {
    config(['tenancy.database.suffix' => '']);
    $tenant = Tenant::factory()->make(['id' => 17, 'user_id' => 1, 'slug' => $slug]);

    expect(fn () => $tenant->database()->getName())->toThrow(LogicException::class);
})->with(['unsafe slug' => '../nour', 'overlong slug' => str_repeat('a', 56)]);

test('a renamed shop keeps its database name reserved even when soft deleted', function (bool $deleted): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $owner = User::factory()->create();
    Event::fake([TenantCreated::class]);
    $tenant = Tenant::factory()->create(['user_id' => $owner->id, 'slug' => 'nour']);
    $tenant->update(['slug' => 'nour-renamed']);
    if ($deleted) {
        $tenant->delete();
    }

    expect(fn () => Tenant::factory()->create(['user_id' => $owner->id, 'slug' => 'nour']))
        ->toThrow(LogicException::class, 'The tenant database name is already reserved or exists.');

    expect(Tenant::withTrashed()->count())->toBe(1);
    expect($tenant->fresh()->database()->getName())->toBe('boutique_nour.sqlite');
    Event::assertDispatchedTimes(TenantCreated::class, 1);
})->with(['renamed' => false, 'renamed and soft deleted' => true]);

test('database naming rejects a prefix that leaves no room for the slug', function (): void {
    config(['tenancy.database.prefix' => str_repeat('a', 64)]);
    $tenant = Tenant::factory()->make(['id' => 17, 'user_id' => 1, 'slug' => 'nour']);

    expect(fn () => $tenant->database()->getName())->toThrow(LogicException::class);
});

test('tenant creation refuses to adopt an existing unregistered database', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $owner = User::factory()->create();
    $slug = 'existing-'.Str::lower(Str::random(16));
    $path = database_path('boutique_'.$slug.'.sqlite');
    if (File::exists($path)) {
        throw new LogicException('The test database path must be unused.');
    }
    File::put($path, 'existing database must remain untouched');

    try {
        expect(fn () => Tenant::factory()->create(['user_id' => $owner->id, 'slug' => $slug]))
            ->toThrow(LogicException::class, 'The tenant database name is already reserved or exists.');

        $this->assertDatabaseCount('tenants', 0);
        expect(File::get($path))->toBe('existing database must remain untouched');
    } finally {
        File::delete($path);
    }
});

test('test seeding creates two independently migrated shops with separate owner credentials', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed([DatabaseSeeder::class, TenantTestSeeder::class]);

    expect(Country::count())->toBe(5);
    expect(User::count())->toBe(2);
    expect(Tenant::count())->toBe(2);
    $this->assertDatabaseCount('domains', 2);

    foreach (['users', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'tenants', 'domains'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    foreach ([1, 2] as $number) {
        $owner = User::where('email', "boutique{$number}@test.com")->firstOrFail();
        $tenant = Tenant::where('slug', "boutique{$number}")->firstOrFail();

        expect($owner->id)->toBe($number);
        expect($owner->name)->toBe("Owner Boutique {$number}");
        expect(Hash::check('password', $owner->password))->toBeTrue();
        expect($tenant->user_id)->toBe($owner->id);
        expect($tenant->id)->toBe($number);
        expect($tenant->getTenantKey())->toBe($number);
        expect($tenant->getIncrementing())->toBeTrue();
        expect($tenant->database()->getName())->toBe("boutique_boutique{$number}.sqlite");
        expect(Str::isUuid($tenant->uuid, 4))->toBeTrue();
        expect($tenant->status)->toBe(TenantStatus::Provisioning);
        expect($tenant->provisioned_at)->toBeNull();
        expect($tenant->domains()->firstOrFail()->domain)->toBe("boutique{$number}.aydra.localhost");

        tenancy()->initialize($tenant);

        expect(Schema::hasTable('countries'))->toBeFalse();

        foreach (['users', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'tenants', 'domains'] as $table) {
            expect(Schema::hasTable($table))->toBeTrue();
        }

        expect(DB::connection('tenant')->table('tenants')->count())->toBe(0);
        expect(DB::connection('tenant')->table('domains')->count())->toBe(0);
        expect(Tenant::count())->toBe(2);
        expect(ShopUser::count())->toBe(1);
        expect(User::count())->toBe(2);
        $local = ShopUser::firstOrFail();
        expect($local->central_user_uuid)->toBe($owner->uuid);
        expect($local->uuid)->not->toBe($owner->uuid);
        expect($local->password)->not->toBe($owner->password);
        expect(Hash::check('password', $local->password))->toBeFalse();
        expect($local->membership_status)->toBe(2);
        expect($local->joined_at)->toBeNull();
        expect(Auth::getDefaultDriver())->toBe('tenant');
        tenancy()->end();
    }

    expect(Auth::getDefaultDriver())->toBe('central');
    expect(DB::getDefaultConnection())->toBe('sqlite');
});

test('retrying test seeding preserves owners passwords tenants domains and local identities', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed([DatabaseSeeder::class, TenantTestSeeder::class]);
    $owner = User::where('email', 'boutique1@test.com')->firstOrFail();
    $owner->update(['email' => 'boutique_1@gmail.com', 'password' => 'changed-password']);
    $tenant = Tenant::where('slug', 'boutique1')->firstOrFail();
    $uuid = $tenant->uuid;
    $domainUuid = $tenant->domains()->firstOrFail()->uuid;
    $databaseName = $tenant->database()->getName();
    tenancy()->initialize($tenant);
    $local = ShopUser::firstOrFail();
    $localUuid = $local->uuid;
    $localHash = $local->password;
    $local->update(['email' => 'boutique_1@gmail.com']);
    tenancy()->end();

    $tenant->setInternal('db_name', 'tenant_'.$uuid.'.sqlite');
    $tenant->save();

    config(['tenancy.saas_base_domain' => 'another-saas.localhost']);
    $this->seed([DatabaseSeeder::class, TenantTestSeeder::class]);

    expect(User::count())->toBe(2);
    expect(Tenant::count())->toBe(2);
    expect(Hash::check('changed-password', $owner->fresh()->password))->toBeTrue();
    expect($owner->fresh()->email)->toBe('boutique1@test.com');
    expect(Tenant::where('slug', 'boutique1')->firstOrFail()->uuid)->toBe($uuid);
    $this->assertDatabaseCount('domains', 2);
    expect($tenant->domains()->firstOrFail()->uuid)->toBe($domainUuid);
    expect($tenant->domains()->firstOrFail()->domain)->toBe('boutique1.another-saas.localhost');
    $this->get('http://boutique1.aydra.localhost/')->assertNotFound();
    $this->get('http://boutique1.another-saas.localhost/')->assertServiceUnavailable()
        ->assertSeeText('Cette boutique est en préparation.');
    tenancy()->initialize($tenant->fresh());
    expect(ShopUser::firstOrFail()->uuid)->toBe($localUuid);
    expect(ShopUser::firstOrFail()->password)->toBe($localHash);
    expect(ShopUser::firstOrFail()->email)->toBe('boutique1@test.com');
    expect($tenant->fresh()->database()->getName())->toBe($databaseName);
    tenancy()->end();
});

test('each domain selects its own database and restores the central context after its response', function (): void {
    config(['app.debug' => true]);
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed([DatabaseSeeder::class, TenantTestSeeder::class]);
    $captured = [];
    Event::listen(TenancyBootstrapped::class, function () use (&$captured): void {
        $captured[] = [ShopUser::firstOrFail()->email, DB::connection('tenant')->getDatabaseName(), Auth::getDefaultDriver()];
    });

    $this->get('http://boutique1.aydra.localhost/')->assertServiceUnavailable()
        ->assertContent("Cette boutique est en préparation.\nID de la boutique : 1\nID du propriétaire (compte central) : 1")
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertHeader('Cache-Control', 'no-store, private');
    expect(tenancy()->initialized)->toBeFalse();
    $this->get('http://boutique2.aydra.localhost/')->assertServiceUnavailable()
        ->assertContent("Cette boutique est en préparation.\nID de la boutique : 2\nID du propriétaire (compte central) : 2");
    expect(tenancy()->initialized)->toBeFalse();

    expect(array_column($captured, 0))->toBe(['boutique1@test.com', 'boutique2@test.com']);
    expect($captured[0][1])->not->toBe($captured[1][1]);
    expect(array_column($captured, 2))->toBe(['tenant', 'tenant']);
    expect(Auth::getDefaultDriver())->toBe('central');
    expect(User::count())->toBe(2);

    config(['app.debug' => false]);
    $this->get('http://boutique1.aydra.localhost/')->assertContent('Cette boutique est en préparation.');

    config(['app.debug' => true]);
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])
        ->get('http://boutique1.aydra.localhost/')->assertContent('Cette boutique est en préparation.');

    $this->app['env'] = 'production';
    $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
        ->get('http://boutique1.aydra.localhost/')->assertContent('Cette boutique est en préparation.');
});

test('the tenant base derives from APP_URL and the central domains stay explicit', function (): void {
    $environmentValues = [];
    $serverValues = [];

    foreach (['APP_URL', 'SAAS_BASE_DOMAIN'] as $key) {
        $environmentValues[$key] = $_ENV[$key] ?? null;
        $serverValues[$key] = $_SERVER[$key] ?? null;
    }

    try {
        $_ENV['APP_URL'] = 'https://Platform.EXAMPLE:8443/application';
        $_SERVER['APP_URL'] = $_ENV['APP_URL'];
        unset($_ENV['SAAS_BASE_DOMAIN'], $_SERVER['SAAS_BASE_DOMAIN']);

        $configuration = require config_path('tenancy.php');

        expect($configuration['saas_base_domain'])->toBe('platform.example');
        expect($configuration['central_domains'])->toBe(['127.0.0.1', 'localhost', 'aydra.localhost']);
    } finally {
        foreach (['APP_URL', 'SAAS_BASE_DOMAIN'] as $key) {
            if ($environmentValues[$key] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $environmentValues[$key];
            }

            if ($serverValues[$key] === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $serverValues[$key];
            }
        }
    }
});

test('tenancy rejects an APP_URL without an application host', function (string $url): void {
    $environmentValue = $_ENV['APP_URL'] ?? null;
    $serverValue = $_SERVER['APP_URL'] ?? null;

    try {
        $_ENV['APP_URL'] = $url;
        $_SERVER['APP_URL'] = $url;

        expect(fn () => require config_path('tenancy.php'))->toThrow(InvalidArgumentException::class);
    } finally {
        if ($environmentValue === null) {
            unset($_ENV['APP_URL']);
        } else {
            $_ENV['APP_URL'] = $environmentValue;
        }

        if ($serverValue === null) {
            unset($_SERVER['APP_URL']);
        } else {
            $_SERVER['APP_URL'] = $serverValue;
        }
    }
})->with(['boolean environment value' => ['false'], 'missing host' => ['invalid-url']]);

test('a separate tenant base domain is not reserved for central routes', function (): void {
    $environmentValue = $_ENV['SAAS_BASE_DOMAIN'] ?? null;
    $serverValue = $_SERVER['SAAS_BASE_DOMAIN'] ?? null;

    try {
        $_ENV['SAAS_BASE_DOMAIN'] = 'shops.aydra.localhost';
        $_SERVER['SAAS_BASE_DOMAIN'] = 'shops.aydra.localhost';

        $configuration = require config_path('tenancy.php');

        expect($configuration['saas_base_domain'])->toBe('shops.aydra.localhost');
        expect($configuration['central_domains'])->toContain('localhost', '127.0.0.1')
            ->not->toContain('shops.aydra.localhost', 'boutique1.shops.aydra.localhost');
    } finally {
        if ($environmentValue === null) {
            unset($_ENV['SAAS_BASE_DOMAIN']);
        } else {
            $_ENV['SAAS_BASE_DOMAIN'] = $environmentValue;
        }

        if ($serverValue === null) {
            unset($_SERVER['SAAS_BASE_DOMAIN']);
        } else {
            $_SERVER['SAAS_BASE_DOMAIN'] = $serverValue;
        }
    }
});

test('tenant and unknown hosts cannot use central login dashboard or Livewire endpoints', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed([DatabaseSeeder::class, TenantTestSeeder::class]);

    $this->get('http://boutique1.aydra.localhost/login')->assertNotFound();
    $this->get('http://boutique1.aydra.localhost/dashboard')->assertNotFound();
    $this->post('http://boutique1.aydra.localhost/livewire/update', [])->assertNotFound();
    $this->get('http://unknown.aydra.localhost/')->assertNotFound();
    $this->get(config('app.url').'/login')->assertOk();
    expect(tenancy()->initialized)->toBeFalse();
});

test('a revoked or unverified domain does not expose the shop', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed([DatabaseSeeder::class, TenantTestSeeder::class]);
    $tenant = Tenant::where('slug', 'boutique1')->firstOrFail();
    $domain = $tenant->domains()->firstOrFail();
    $domain->update(['verification_status' => 1]);

    $this->get('http://boutique1.aydra.localhost/')->assertNotFound();
    $domain->delete();
    $this->get('http://boutique1.aydra.localhost/')->assertNotFound();
    expect(tenancy()->initialized)->toBeFalse();
});

test('internal tenant identifiers are not exposed in production or for disabled accounts and shops', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed([DatabaseSeeder::class, TenantTestSeeder::class]);
    $tenant = Tenant::where('slug', 'boutique1')->firstOrFail();
    $this->app->instance('env', 'production');

    $this->get('http://boutique1.aydra.localhost/')->assertServiceUnavailable()
        ->assertDontSee('The id of the current tenant');

    $this->app->instance('env', 'testing');
    $tenant->owner->forceFill(['status' => 2])->save();
    $this->get('http://boutique1.aydra.localhost/')->assertServiceUnavailable();
    $tenant->owner->forceFill(['status' => 1])->save();
    $tenant->status = TenantStatus::Suspended;
    $tenant->save();
    $this->get('http://boutique1.aydra.localhost/')->assertServiceUnavailable();
    expect(tenancy()->initialized)->toBeFalse();
});

test('a shop outside the test seeder keeps the preparation response', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $tenant = Tenant::factory()->create();
    $tenant->domains()->create(['domain' => 'normal.aydra.localhost', 'type' => 1, 'is_primary' => true, 'verification_status' => 2, 'verified_at' => now()]);

    $this->get('http://normal.aydra.localhost/')->assertServiceUnavailable()
        ->assertDontSee('The id of the current tenant');
});

test('tenant database creation waits for commit and a rollback does not create a database', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $owner = User::factory()->create();
    DB::beginTransaction();
    $tenant = Tenant::factory()->create(['user_id' => $owner->id]);
    $databaseName = $tenant->database()->getName();
    expect(File::exists(database_path($databaseName)))->toBeFalse();
    DB::rollBack();

    expect(File::exists(database_path($databaseName)))->toBeFalse();
    expect(Tenant::count())->toBe(0);

    DB::beginTransaction();
    $tenant = Tenant::factory()->create(['user_id' => $owner->id]);
    $databaseName = $tenant->database()->getName();
    $tenant->update(['shop_name' => 'Renamed before provisioning', 'slug' => 'renamed-shop']);
    DB::commit();

    expect(File::exists(database_path($databaseName)))->toBeTrue();
    expect($tenant->fresh()->database()->getName())->toBe($databaseName);
    $tenant->update(['shop_name' => 'Renamed after provisioning', 'slug' => 'renamed-again']);
    expect($tenant->fresh()->database()->getName())->toBe($databaseName);
    tenancy()->initialize($tenant);
    expect(ShopUser::firstOrFail()->central_user_uuid)->toBe($owner->uuid);
    expect($tenant->fresh()->schema_version)->toBe(DB::connection('tenant')->table('migrations')->orderByDesc('id')->value('migration'));
    tenancy()->end();
});

test('tenant ownership and public identifiers cannot be changed', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed([DatabaseSeeder::class, TenantTestSeeder::class]);
    $tenant = Tenant::where('slug', 'boutique1')->firstOrFail();
    $ownerId = $tenant->user_id;
    $tenant->user_id = User::where('email', 'boutique2@test.com')->firstOrFail()->id;
    expect(fn () => $tenant->save())->toThrow(LogicException::class);
    expect($tenant->fresh()->user_id)->toBe($ownerId);

    $tenant = $tenant->fresh();
    $tenant->update(['user_id' => User::where('email', 'boutique2@test.com')->firstOrFail()->id]);
    expect($tenant->fresh()->user_id)->toBe($ownerId);

    $tenant = $tenant->fresh();
    $uuid = $tenant->uuid;
    $tenant->uuid = Str::uuid()->toString();
    expect(fn () => $tenant->save())->toThrow(LogicException::class);
    expect($tenant->fresh()->uuid)->toBe($uuid);
});

test('a failed migration remains failed and a retry installs the missing local tables', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $owner = User::factory()->create();
    $paths = config('tenancy.migration_parameters.--path');
    config(['tenancy.migration_parameters.--path' => [base_path('tests/nonexistent-migrations')]]);

    expect(fn () => Tenant::factory()->create(['user_id' => $owner->id]))->toThrow(Exception::class);
    $tenant = Tenant::firstOrFail();
    expect($tenant->status)->toBe(TenantStatus::ProvisioningFailed);
    expect($tenant->provisioned_at)->toBeNull();
    expect(tenancy()->initialized)->toBeFalse();

    config(['tenancy.migration_parameters.--path' => $paths]);
    app(TenantProvisioner::class)->provision($tenant);

    expect($tenant->fresh()->status)->toBe(TenantStatus::Provisioning);
    tenancy()->initialize($tenant);
    expect(ShopUser::count())->toBe(1);
    tenancy()->end();
});

test('normal seeding creates only reference countries in local and production environments', function (string $environment): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->app->instance('env', $environment);
    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true, '--no-interaction' => true])->assertSuccessful();
    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true, '--no-interaction' => true])->assertSuccessful();

    expect(Country::count())->toBe(5);
    expect(User::count())->toBe(0);
    expect(Tenant::count())->toBe(0);
    $this->assertDatabaseCount('domains', 0);
    expect(fn () => app(TenantTestSeeder::class)->run())->toThrow(LogicException::class);
})->with(['local', 'production']);

test('two active primary domains cannot be assigned to the same shop', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed([DatabaseSeeder::class, TenantTestSeeder::class]);
    $tenant = Tenant::where('slug', 'boutique1')->firstOrFail();

    expect(fn () => $tenant->domains()->create([
        'domain' => 'second.aydra.localhost',
        'is_primary' => true,
    ]))->toThrow(QueryException::class);
    expect($tenant->domains()->count())->toBe(1);
});

test('raw updates cannot transfer a tenant or change the local owner identity', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed([DatabaseSeeder::class, TenantTestSeeder::class]);
    $tenant = Tenant::where('slug', 'boutique1')->firstOrFail();
    $ownerId = $tenant->user_id;
    $otherOwner = User::where('email', 'boutique2@test.com')->firstOrFail();

    expect(fn () => DB::table('tenants')->where('id', $tenant->id)->update(['user_id' => $otherOwner->id]))->toThrow(QueryException::class);
    expect($tenant->fresh()->user_id)->toBe($ownerId);

    tenancy()->initialize($tenant);
    $local = ShopUser::firstOrFail();
    expect(fn () => DB::table('users')->where('id', $local->id)->update(['central_user_uuid' => $otherOwner->uuid]))->toThrow(QueryException::class);
    expect($local->fresh()->central_user_uuid)->toBe($tenant->owner->uuid);
    tenancy()->end();
});

test('cache sessions and private disk paths remain separate across tenant switches', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed([DatabaseSeeder::class, TenantTestSeeder::class]);
    config(['cache.default' => 'database', 'session.driver' => 'database']);
    Cache::forgetDriver();
    Cache::put('context-test', 'central', 600);
    $centralCookie = config('session.cookie');
    $centralDisk = Storage::disk('local')->path('document.pdf');
    $centralSession = app('session.store');
    $first = Tenant::where('slug', 'boutique1')->firstOrFail();
    $second = Tenant::where('slug', 'boutique2')->firstOrFail();

    tenancy()->initialize($first);
    expect(Cache::get('context-test'))->toBeNull();
    Cache::put('context-test', 'first', 600);
    $firstCookie = config('session.cookie');
    $firstDisk = Storage::disk('local')->path('document.pdf');
    $firstSession = app('session.store');
    expect($firstSession)->not->toBe($centralSession);
    $firstSession->put('context-test', 'first');
    $firstSession->save();
    expect(DB::connection('tenant')->table('sessions')->count())->toBe(1);
    expect(DB::connection('sqlite')->table('sessions')->count())->toBe(0);
    tenancy()->end();

    tenancy()->initialize($second);
    expect(DB::connection('tenant')->table('sessions')->count())->toBe(0);
    expect(Cache::get('context-test'))->toBeNull();
    Cache::put('context-test', 'second', 600);
    expect(config('session.cookie'))->not->toBe($firstCookie);
    expect(Storage::disk('local')->path('document.pdf'))->not->toBe($firstDisk);
    expect(app('session.store'))->not->toBe($firstSession);
    tenancy()->end();

    tenancy()->initialize($first);
    expect(Cache::get('context-test'))->toBe('first');
    tenancy()->end();

    expect(Cache::get('context-test'))->toBe('central');
    expect(config('session.cookie'))->toBe($centralCookie);
    expect(Storage::disk('local')->path('document.pdf'))->toBe($centralDisk);
});

test('a tenant job is stored centrally and carries its numeric tenant id', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed([DatabaseSeeder::class, TenantTestSeeder::class]);
    $tenant = Tenant::where('slug', 'boutique1')->firstOrFail();
    tenancy()->initialize($tenant);

    Queue::connection('database')->push(new CreateDatabase($tenant));

    $job = DB::connection('sqlite')->table('jobs')->first();
    expect($job)->not->toBeNull();
    $payload = json_decode($job->payload, true, flags: JSON_THROW_ON_ERROR);
    expect($payload['tenant_id'])->toBe($tenant->id);
    expect(DB::connection('tenant')->table('jobs')->count())->toBe(0);
    tenancy()->end();
});
