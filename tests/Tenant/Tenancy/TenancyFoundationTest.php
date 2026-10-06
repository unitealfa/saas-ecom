<?php

use App\Models\Central\Country;
use App\Models\Central\TenantStatus;
use App\Models\Tenant;
use App\Models\Tenant\User as ShopUser;
use App\Models\User;
use App\Services\Central\TenantProvisioner;
use Database\Seeders\Central\DemoTenantSeeder;
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
use Stancl\Tenancy\Jobs\CreateDatabase;

beforeEach(function (): void {
    config(['tenancy.database.suffix' => '.sqlite']);
});

afterEach(function (): void {
    tenancy()->end();

    if (Schema::hasTable('tenants')) {
        foreach (Tenant::withTrashed()->get() as $tenant) {
            $name = $tenant->database()->getName();

            if (config('tenancy.database.central_connection') !== 'sqlite' || ! preg_match('/^tenant_[a-f0-9-]{36}\.sqlite$/D', $name)) {
                throw new LogicException('Refusing to remove a database outside this SQLite test.');
            }

            File::delete(database_path($name));
        }
    }
});

test('demo seeding creates two independently migrated shops with separate owner credentials', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed(DatabaseSeeder::class);

    expect(Country::count())->toBe(5);
    expect(User::count())->toBe(2);
    expect(Tenant::count())->toBe(2);
    $this->assertDatabaseCount('domains', 2);

    foreach (['users', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'tenants', 'domains'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    foreach ([1, 2] as $number) {
        $owner = User::where('email', "boutique_{$number}@gmail.com")->firstOrFail();
        $tenant = Tenant::where('slug', "boutique{$number}")->firstOrFail();

        expect($owner->last_name)->toBe("user_{$number}");
        expect(Hash::check('password', $owner->password))->toBeTrue();
        expect($tenant->user_id)->toBe($owner->id);
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

test('retrying demo seeding preserves owners passwords tenants domains and local identities', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed(DatabaseSeeder::class);
    $owner = User::where('email', 'boutique_1@gmail.com')->firstOrFail();
    $owner->update(['password' => 'changed-password']);
    $tenant = Tenant::where('slug', 'boutique1')->firstOrFail();
    $uuid = $tenant->uuid;
    tenancy()->initialize($tenant);
    $local = ShopUser::firstOrFail();
    $localUuid = $local->uuid;
    $localHash = $local->password;
    tenancy()->end();

    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(2);
    expect(Tenant::count())->toBe(2);
    expect(Hash::check('changed-password', $owner->fresh()->password))->toBeTrue();
    expect(Tenant::where('slug', 'boutique1')->firstOrFail()->uuid)->toBe($uuid);
    $this->assertDatabaseCount('domains', 2);
    tenancy()->initialize($tenant);
    expect(ShopUser::firstOrFail()->uuid)->toBe($localUuid);
    expect(ShopUser::firstOrFail()->password)->toBe($localHash);
    tenancy()->end();
});

test('each domain selects its own database and restores the central context after its response', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed(DatabaseSeeder::class);
    $captured = [];
    Event::listen(TenancyBootstrapped::class, function () use (&$captured): void {
        $captured[] = [ShopUser::firstOrFail()->email, DB::connection('tenant')->getDatabaseName(), Auth::getDefaultDriver()];
    });

    $this->get('http://boutique1.aydra.localhost/')->assertServiceUnavailable();
    expect(tenancy()->initialized)->toBeFalse();
    $this->get('http://boutique2.aydra.localhost/')->assertServiceUnavailable();
    expect(tenancy()->initialized)->toBeFalse();

    expect(array_column($captured, 0))->toBe(['boutique_1@gmail.com', 'boutique_2@gmail.com']);
    expect($captured[0][1])->not->toBe($captured[1][1]);
    expect(array_column($captured, 2))->toBe(['tenant', 'tenant']);
    expect(Auth::getDefaultDriver())->toBe('central');
    expect(User::count())->toBe(2);
});

test('tenant and unknown hosts cannot use central login dashboard or Livewire endpoints', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed(DatabaseSeeder::class);

    $this->get('http://boutique1.aydra.localhost/login')->assertNotFound();
    $this->get('http://boutique1.aydra.localhost/dashboard')->assertNotFound();
    $this->post('http://boutique1.aydra.localhost/livewire/update', [])->assertNotFound();
    $this->get('http://unknown.aydra.localhost/')->assertNotFound();
    $this->get(config('app.url').'/login')->assertOk();
    expect(tenancy()->initialized)->toBeFalse();
});

test('a revoked or unverified domain does not expose the shop', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed(DatabaseSeeder::class);
    $tenant = Tenant::where('slug', 'boutique1')->firstOrFail();
    $domain = $tenant->domains()->firstOrFail();
    $domain->update(['verification_status' => 1]);

    $this->get('http://boutique1.aydra.localhost/')->assertNotFound();
    $domain->delete();
    $this->get('http://boutique1.aydra.localhost/')->assertNotFound();
    expect(tenancy()->initialized)->toBeFalse();
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
    DB::commit();

    expect(File::exists(database_path($databaseName)))->toBeTrue();
    tenancy()->initialize($tenant);
    expect(ShopUser::firstOrFail()->central_user_uuid)->toBe($owner->uuid);
    tenancy()->end();
});

test('tenant ownership and public identifiers cannot be changed', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed(DatabaseSeeder::class);
    $tenant = Tenant::where('slug', 'boutique1')->firstOrFail();
    $ownerId = $tenant->user_id;
    $tenant->user_id = User::where('email', 'boutique_2@gmail.com')->firstOrFail()->id;
    expect(fn () => $tenant->save())->toThrow(LogicException::class);
    expect($tenant->fresh()->user_id)->toBe($ownerId);

    $tenant = $tenant->fresh();
    $tenant->update(['user_id' => User::where('email', 'boutique_2@gmail.com')->firstOrFail()->id]);
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

test('production seeding does not create the demonstration accounts or shops', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->app->instance('env', 'production');
    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true, '--no-interaction' => true])->assertSuccessful();

    expect(Country::count())->toBe(5);
    expect(User::count())->toBe(0);
    expect(Tenant::count())->toBe(0);
    expect(fn () => app(DemoTenantSeeder::class)->run())->toThrow(LogicException::class);
});

test('two active primary domains cannot be assigned to the same shop', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed(DatabaseSeeder::class);
    $tenant = Tenant::where('slug', 'boutique1')->firstOrFail();

    expect(fn () => $tenant->domains()->create([
        'domain' => 'second.aydra.localhost',
        'is_primary' => true,
    ]))->toThrow(QueryException::class);
    expect($tenant->domains()->count())->toBe(1);
});

test('raw updates cannot transfer a tenant or change the local owner identity', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed(DatabaseSeeder::class);
    $tenant = Tenant::where('slug', 'boutique1')->firstOrFail();
    $ownerId = $tenant->user_id;
    $otherOwner = User::where('email', 'boutique_2@gmail.com')->firstOrFail();

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
    $this->seed(DatabaseSeeder::class);
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

test('a tenant job is stored centrally and carries its public tenant UUID', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed(DatabaseSeeder::class);
    $tenant = Tenant::where('slug', 'boutique1')->firstOrFail();
    tenancy()->initialize($tenant);

    Queue::connection('database')->push(new CreateDatabase($tenant));

    $job = DB::connection('sqlite')->table('jobs')->first();
    expect($job)->not->toBeNull();
    $payload = json_decode($job->payload, true, flags: JSON_THROW_ON_ERROR);
    expect($payload['tenant_id'])->toBe($tenant->uuid);
    expect(DB::connection('tenant')->table('jobs')->count())->toBe(0);
    tenancy()->end();
});
