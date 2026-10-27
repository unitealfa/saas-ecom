<?php

use App\DTOs\Central\Tenants\TenantData;
use App\DTOs\Tenant\Shop\ShopSettings;
use App\Enums\Central\Deployments\StatusEnum as DeploymentStatus;
use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Enums\Central\Users\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Countries\Country;
use App\Models\Central\Deployments\TenantSchemaDeployment;
use App\Models\Central\Roles\Role;
use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Shop\Shop;
use App\Models\User;
use App\Services\Central\Tenants\TenantCreator;
use App\Services\Central\Tenants\TenantProvisioner;
use App\Services\Central\Users\UserApprovalService;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\CountrySeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

beforeEach(function (): void {
    Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
    $this->seed([CountrySeeder::class, AuthorizationSeeder::class]);
    Notification::fake();
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::findByName('root', 'central'));
    $this->owner = User::factory()->create(['legal_profile_version' => 1, 'legal_verified_by_id' => $this->admin->id,
        'legal_verification_status' => VerificationStatusEnum::VERIFIED, 'legal_verified_at' => now()]);
});

afterEach(function (): void {
    tenancy()->end();
    foreach (Tenant::withTrashed()->get() as $tenant) {
        TestCase::dropIsolatedMysqlDatabase($tenant->database()->getName());
    }
});

function shopWorkflowPayload(string $name = 'Nour'): array
{
    return ['shop_name' => $name, 'slug' => Str::slug($name), 'creation_key' => (string) Str::uuid(),
        'settings' => ['cart_lifetime_days' => 19]];
}

function runShopWorkflowWorker(): void
{
    Artisan::call('queue:work', ['connection' => 'central-provisioning', '--queue' => 'tenant-provisioning',
        '--stop-when-empty' => true, '--sleep' => 0, '--no-interaction' => true]);
}

test('a public registration completes verification approval first shop provisioning and dashboard access', function (): void {
    $this->post(route('register.store'), [
        'last_name' => 'Parcours complet', 'email' => 'full-journey@example.test', 'phone' => '0555123456',
        'password' => 'password', 'password_confirmation' => 'password', 'country_uuid' => $this->owner->country->uuid,
    ])->assertSessionHasNoErrors()->assertRedirect(route('verification.notice'));
    $merchant = User::where('email', 'full-journey@example.test')->firstOrFail();
    Notification::assertSentTo($merchant, VerifyEmail::class);
    expect(Tenant::count())->toBe(0)->and($merchant->hasVerifiedEmail())->toBeFalse();
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $merchant->id, 'hash' => sha1($merchant->email),
    ]);
    $this->get($url)->assertRedirect(route('login'));
    $this->assertGuest('central');
    $this->post(route('login.store'), ['email' => $merchant->email, 'password' => 'password'])->assertRedirect(route('registration.status'));
    $this->actingAs($this->admin, 'central')->post(route('central.users.approve', $merchant), [
        'expected_status' => StatusEnum::PENDING->value, 'legal_profile_version' => 1,
    ])->assertSessionHasNoErrors();
    expect(Tenant::count())->toBe(0);
    $this->actingAs($merchant->fresh(), 'central')->get(route('dashboard'))->assertRedirect(route('owner.first-shop'));
    $this->post(route('owner.first-shop.store'), shopWorkflowPayload('Parcours complet'))->assertSessionHasNoErrors();
    $shop = Tenant::where('user_id', $merchant->id)->sole();
    expect($shop->status)->toBe(TenantStatus::PROVISIONING);
    $this->get(route('dashboard'))->assertRedirect(route('registration.status'));
    runShopWorkflowWorker();
    $shop->refresh();
    expect($shop->status)->toBe(TenantStatus::ACTIVE)->and($shop->provisioned_at)->not->toBeNull();
    $shop->run(function () use ($shop, $merchant): void {
        expect(Shop::sole()->tenant_uuid)->toBe($shop->uuid);
        expect(App\Models\Tenant\Users\User::where('central_user_uuid', $merchant->uuid)->count())->toBe(1);
    });
    $this->actingAs($merchant->fresh(), 'central')->get(route('dashboard'))->assertOk();
    expect(DB::table('jobs')->count())->toBe(0)->and(DB::table('failed_jobs')->count())->toBe(0);
});

test('first shop is mandatory then additional shops are created only by request within quota', function (): void {
    $this->actingAs($this->owner, 'central');
    $this->get(route('dashboard'))->assertRedirect(route('owner.first-shop'));
    $this->get(route('owner.first-shop'))->assertOk();
    expect(Tenant::count())->toBe(0);
    $payload = shopWorkflowPayload();
    $this->post(route('owner.first-shop.store'), $payload)->assertSessionHasNoErrors()->assertRedirect(route('registration.status'));
    $this->post(route('owner.first-shop.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
    expect(Tenant::count())->toBe(1)->and(DB::table('jobs')->count())->toBe(1);
    $this->get(route('dashboard'))->assertRedirect(route('registration.status'));
    runShopWorkflowWorker();
    expect(Tenant::firstOrFail()->status)->toBe(TenantStatus::ACTIVE);
    // The in-process test worker resets auth guards; production workers run in a separate process.
    $this->actingAs($this->owner->fresh(), 'central');
    $this->get(route('dashboard'))->assertOk();
    $this->get(route('owner.shops.create'))->assertOk();
    $second = shopWorkflowPayload('Amine');
    $this->post(route('owner.shops.store'), $second)->assertSessionHasNoErrors()->assertRedirect();
    $this->post(route('owner.shops.store'), $second)->assertSessionHasNoErrors()->assertRedirect();
    runShopWorkflowWorker();
    $this->actingAs($this->owner->fresh(), 'central');
    expect(Tenant::count())->toBe(2)->and(Tenant::where('status', TenantStatus::ACTIVE)->count())->toBe(2);
    $this->postJson(route('owner.shops.store'), shopWorkflowPayload('Third'))
        ->assertUnprocessable()->assertJsonValidationErrors('shop_name');
    expect(Tenant::count())->toBe(2)->and(DB::table('jobs')->count())->toBe(0);
});

test('pending refused and unverified owners cannot create shops through direct URLs', function (StatusEnum $status, bool $verified): void {
    $this->owner->forceFill(['status' => $status, 'email_verified_at' => $verified ? now() : null])->save();
    $this->actingAs($this->owner, 'central')->postJson(route('owner.first-shop.store'), shopWorkflowPayload())->assertForbidden();
    $this->postJson(route('owner.shops.store'), shopWorkflowPayload())->assertForbidden();
    expect(Tenant::count())->toBe(0)->and(DB::table('jobs')->count())->toBe(0);
})->with([[StatusEnum::PENDING, true], [StatusEnum::REJECTED, true], [StatusEnum::ACTIVE, false]]);

test('registration and approval never create a shop or enqueue its provisioning', function (): void {
    $this->post(route('register.store'), ['last_name' => 'New Owner', 'email' => 'public-owner@example.test',
        'phone' => '0779107446', 'country_uuid' => Country::where('code', 'DZ')->firstOrFail()->uuid,
        'password' => 'password', 'password_confirmation' => 'password'])->assertSessionHasNoErrors();
    $newOwner = User::where('email', 'public-owner@example.test')->firstOrFail();
    expect(Tenant::count())->toBe(0)->and(DB::table('jobs')->count())->toBe(0);
    app(UserApprovalService::class)->decide($this->admin, $newOwner, true, null, 1, StatusEnum::PENDING);
    $newOwner->refresh()->markEmailAsVerified();
    expect(Tenant::count())->toBe(0)->and(DB::table('jobs')->count())->toBe(0);
    $this->actingAs($newOwner->fresh(), 'central')->get(route('dashboard'))->assertRedirect(route('owner.first-shop'));
});

test('a failed job can resume the same shop with its original settings and no duplicate', function (): void {
    $this->actingAs($this->owner, 'central')->post(route('owner.first-shop.store'), shopWorkflowPayload())->assertSessionHasNoErrors();
    $tenant = Tenant::firstOrFail();
    $realProvisioner = app(TenantProvisioner::class);
    $this->mock(TenantProvisioner::class, function ($mock): void {
        $mock->shouldReceive('provision')->once()->andThrow(new RuntimeException('Transient test failure.'));
    });
    Artisan::call('queue:work', ['connection' => 'central-provisioning', '--once' => true, '--no-interaction' => true]);
    expect($tenant->fresh()->status)->toBe(TenantStatus::PROVISIONING_FAILED)
        ->and($tenant->fresh()->getInternal('shop_settings')['cart_lifetime_days'])->toBe(19);
    app()->instance(TenantProvisioner::class, $realProvisioner);
    $this->get(route('registration.status'))->assertOk()->assertSee('Relancer la préparation');
    $this->post(route('owner.shops.retry', $tenant))->assertSessionHasNoErrors();
    $this->post(route('owner.shops.retry', $tenant))->assertSessionHasNoErrors();
    runShopWorkflowWorker();
    expect(Tenant::count())->toBe(1)->and($tenant->fresh()->status)->toBe(TenantStatus::ACTIVE)
        ->and(TenantSchemaDeployment::firstOrFail()->status)->toBe(DeploymentStatus::SUCCEEDED);
    $tenant->run(fn () => expect(Shop::sole()->cart_lifetime_days)->toBe(19));
    $this->travel(31)->seconds();
    runShopWorkflowWorker();
    expect(DB::table('jobs')->count())->toBe(0)->and(Tenant::count())->toBe(1);
});

test('the queue reservation rolls back atomically with an aborted shop creation', function (): void {
    $payload = shopWorkflowPayload();
    try {
        DB::transaction(function () use ($payload): void {
            app(TenantCreator::class)->createFirst($this->owner, new TenantData(
                $payload['shop_name'], $payload['slug'], $payload['creation_key'], ShopSettings::fromArray(),
            ));
            throw new RuntimeException('Abort request.');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Abort request.');
    }
    expect(Tenant::count())->toBe(0)->and(DB::table('jobs')->count())->toBe(0)->and(TenantSchemaDeployment::count())->toBe(0);
});

test('a second MySQL connection cannot acquire the lease held by the provisioning worker', function (): void {
    $this->actingAs($this->owner, 'central')->post(route('owner.first-shop.store'), shopWorkflowPayload())->assertSessionHasNoErrors();
    $tenant = Tenant::firstOrFail();
    config(['database.connections.provisioning_probe' => config('database.connections.mysql')]);
    $acquired = null;
    $this->mock(TenantProvisioner::class, function ($mock) use ($tenant, &$acquired): void {
        $mock->shouldReceive('provision')->once()->andReturnUsing(function () use ($tenant, &$acquired): void {
            $lock = 'aydra:provision:'.hash('sha1', DB::connection('mysql')->getDatabaseName().':'.$tenant->id);
            $probe = DB::connection('provisioning_probe');
            $acquired = (int) $probe->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lock])->acquired;
            $probe->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
            throw new RuntimeException('End of lease probe.');
        });
    });
    try {
        Artisan::call('queue:work', ['connection' => 'central-provisioning', '--once' => true, '--no-interaction' => true]);
        expect($acquired)->toBe(0)->and($tenant->fresh()->status)->toBe(TenantStatus::PROVISIONING_FAILED);
    } finally {
        DB::purge('provisioning_probe');
    }
});

test('a merchant cannot retry another merchants shop by changing the URL', function (): void {
    $this->actingAs($this->owner, 'central')->post(route('owner.first-shop.store'), shopWorkflowPayload())->assertSessionHasNoErrors();
    $tenant = Tenant::firstOrFail();
    $tenant->status = TenantStatus::PROVISIONING_FAILED;
    $tenant->save();
    TenantSchemaDeployment::where('tenant_id', $tenant->id)->update(['status' => DeploymentStatus::FAILED]);
    $other = User::factory()->create(['legal_profile_version' => 1, 'legal_verified_by_id' => $this->admin->id,
        'legal_verification_status' => VerificationStatusEnum::VERIFIED, 'legal_verified_at' => now()]);
    $this->actingAs($other, 'central')->post(route('owner.shops.retry', $tenant))->assertForbidden();
    expect(DB::table('jobs')->count())->toBe(1)->and($tenant->fresh()->status)->toBe(TenantStatus::PROVISIONING_FAILED);
});
