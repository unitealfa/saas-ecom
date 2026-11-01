<?php

use App\Enums\Central\Deployments\OperationEnum;
use App\Enums\Central\Deployments\StatusEnum as DeploymentStatus;
use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Enums\Central\Users\JourneyState;
use App\Enums\Central\Users\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Http\Controllers\Central\Owner\Tenants\FirstTenantController;
use App\Http\Controllers\Central\Owner\Tenants\TenantController as OwnerTenantController;
use App\Jobs\Central\ProvisionTenantDatabase;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Deployments\TenantSchemaDeployment;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Roles\Role;
use App\Models\Central\Tenants\Tenant;
use App\Models\User;
use App\Services\Central\Auth\RegistrationJourney;
use App\Services\Central\Users\UserApprovalService;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\TenantCreated;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;

test('status refresh spam is limited per merchant without modifying the journey', function (): void {
    $merchant = journeyMerchant(['status' => StatusEnum::PENDING]);
    $this->actingAs($merchant, 'central');
    for ($i = 0; $i < 20; $i++) {
        $this->getJson(route('registration.status'))->assertOk()->assertJsonPath('state', 'pending');
    }
    $this->getJson(route('registration.status'))->assertStatus(429)->assertHeader('Retry-After');
    expect($merchant->fresh()->status)->toBe(StatusEnum::PENDING)
        ->and(Tenant::where('user_id', $merchant->id)->exists())->toBeFalse();

    $other = journeyMerchant(['status' => StatusEnum::PENDING]);
    $this->actingAs($other, 'central')->getJson(route('registration.status'))->assertOk();
    $this->travel(61)->seconds();
    $this->actingAs($merchant, 'central')->getJson(route('registration.status'))->assertOk();
});

test('SaaS staff is exempt from the merchant status refresh limit', function (): void {
    $this->seed(AuthorizationSeeder::class);
    $staff = User::factory()->create();
    $staff->assignRole(Role::findByName('root', 'central'));
    $this->actingAs($staff, 'central');
    for ($i = 0; $i < 21; $i++) {
        $this->getJson(route('registration.status'))->assertOk()->assertJsonPath('state', 'ready');
    }
});

test('the real status page prevents parallel clicks and respects server backoff', function (): void {
    $merchant = journeyMerchant(['status' => StatusEnum::PENDING]);
    $response = $this->actingAs($merchant, 'central')->withSession(['status' => 'status-message-once'])
        ->get(route('registration.status'))->assertOk();
    expect(substr_count($response->getContent(), 'status-message-once'))->toBe(1);
    $script = <<<'JS'
    const fs = require('fs');
    const vm = require('vm');
    const assert = require('assert/strict');
    const html = fs.readFileSync(0, 'utf8');
    const expression = html.match(/x-data="(\{\s*version:[\s\S]*?\})"/)[1]
      .replace(/&quot;/g, '"').replace(/&#039;/g, "'").replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>');
    let calls = 0, resolveFetch;
    const timers = [];
    const sandbox = {
      document: {hidden: false},
      window: {setTimeout: (callback, delay) => timers.push({callback, delay}), clearTimeout() {},
        clearInterval() {}, setInterval() {}, location: {replace() { throw new Error('Unexpected redirect'); }}},
      fetch: () => { calls++; return new Promise(resolve => { resolveFetch = resolve; }); }
    };
    (async () => {
      const state = vm.runInNewContext('(' + expression + ')', sandbox);
      const first = state.refresh();
      await Promise.all(Array.from({length: 30}, () => state.refresh()));
      assert.equal(calls, 1);
      resolveFetch({status: 200, ok: true, json: async () => ({state: state.state, version: state.version})});
      await first;
      await state.refresh();
      assert.equal(calls, 1);
      assert.equal(timers.at(-1).delay, 5000);
      timers.at(-1).callback();
      sandbox.fetch = async () => { calls++; return {status: 429, headers: {get: () => '60'}}; };
      await state.refresh();
      assert.equal(state.refreshLimited, true);
      assert.equal(timers.at(-1).delay, 60000);
      await state.refresh();
      assert.equal(calls, 2);
      console.log('safe');
    })().catch(error => { console.error(error); process.exitCode = 1; });
    JS;
    $process = new Process(['node', '-e', $script], base_path());
    $process->setInput($response->getContent())->mustRun();
    expect(trim($process->getOutput()))->toBe('safe');
});

function journeyMerchant(array $attributes = []): User
{
    if (($attributes['status'] ?? StatusEnum::ACTIVE) === StatusEnum::ACTIVE) {
        $validator = User::factory()->create();
        $attributes += ['legal_profile_version' => 1, 'legal_verified_by_id' => $validator->id,
            'legal_verification_status' => VerificationStatusEnum::VERIFIED, 'legal_verified_at' => now()];
    }

    return User::factory()->create($attributes);
}

test('controller corrections refuse a shop form when its authenticated owner no longer exists', function (string $controller): void {
    $owner = User::factory()->create();
    $owner->forceDelete();
    $this->actingAs($owner, 'central');
    $request = Request::create('/premiere-boutique');

    try {
        app()->call([app($controller), 'create'], ['request' => $request]);
        $this->fail('An unavailable owner must not open the shop form.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
})->with([FirstTenantController::class, OwnerTenantController::class]);

test('registration journey priorities override later states and ready shops', function (StatusEnum $status, bool $emailVerified, bool $legalVerified, bool $readyShop, bool $staff, JourneyState $expected): void {
    $validator = User::factory()->create();
    $user = User::factory()->create([
        'status' => $status, 'email_verified_at' => $emailVerified ? now() : null,
        'legal_profile_version' => 1,
        'legal_verification_status' => $legalVerified ? VerificationStatusEnum::VERIFIED : VerificationStatusEnum::PENDING,
        'legal_verified_at' => $legalVerified ? now() : null,
        'legal_verified_by_id' => $legalVerified ? $validator->id : null,
    ]);
    if ($staff) {
        Permission::findOrCreate('saas.users.view', 'central');
        $user->assignRole(Role::createWithPermissions('journey-staff', ['saas.users.view' => 9999]));
    }
    if ($readyShop) {
        Event::fake([TenantCreated::class]);
        Tenant::factory()->create(['user_id' => $user->id, 'status' => TenantStatus::ACTIVE,
            'schema_version' => 'completed-schema', 'provisioned_at' => now()]);
        Event::assertDispatchedTimes(TenantCreated::class, 1);
    }

    $journey = app(RegistrationJourney::class);

    expect($journey->state($user))->toBe($expected);
    expect($journey->progress($user)['state'])->toBe($expected->value);
})->with([
    'unverified before rejection' => [StatusEnum::REJECTED, false, false, true, false, JourneyState::VERIFICATION],
    'pending before a ready shop' => [StatusEnum::PENDING, true, true, true, false, JourneyState::PENDING],
    'rejected before a ready shop' => [StatusEnum::REJECTED, true, false, true, false, JourneyState::REJECTED],
    'unverified staff' => [StatusEnum::ACTIVE, false, false, false, true, JourneyState::VERIFICATION],
    'staff needs neither legal approval nor shops' => [StatusEnum::ACTIVE, true, false, false, true, JourneyState::READY],
    'legal approval before a ready shop' => [StatusEnum::ACTIVE, true, false, true, false, JourneyState::PENDING],
]);

test('merchant authentication follows email approval and shop readiness', function (StatusEnum $status, bool $verified, ?TenantStatus $shopStatus, bool $prepared, string $state, string $destination): void {
    $merchant = journeyMerchant(['status' => $status, 'email_verified_at' => $verified ? now() : null]);
    if ($shopStatus !== null) {
        Event::fake([TenantCreated::class]);
        Tenant::factory()->create([
            'user_id' => $merchant->id, 'status' => $shopStatus,
            'schema_version' => $prepared ? 'completed-schema' : null,
            'provisioned_at' => $prepared ? now() : null,
        ]);
        Event::assertDispatched(TenantCreated::class);
    }

    $this->post(route('login.store'), ['email' => $merchant->email, 'password' => 'password'])
        ->assertSessionHasNoErrors()->assertRedirect(route($destination));
    $this->assertAuthenticatedAs($merchant, 'central');
    $progress = $this->getJson(route('registration.status'))->assertOk()
        ->assertJsonPath('state', $state)->assertJsonPath('redirect', route($destination))
        ->assertJsonMissingPath('user_id')->assertJsonMissingPath('password');
    expect(app(RegistrationJourney::class)->state($merchant))->toBe(JourneyState::from($state))
        ->and($progress->json('version'))->toBe(hash('sha256', json_encode([
            $state, $progress->json('reason'), $progress->json('shops'),
        ], JSON_THROW_ON_ERROR)));
    if ($state === 'ready') {
        $this->get(route('dashboard'))->assertOk();
    } else {
        $this->get(route('dashboard'))->assertRedirect(route($destination));
        $this->getJson(route('dashboard'))->assertForbidden();
    }
})->with([
    'pending and unverified' => [StatusEnum::PENDING, false, null, false, 'verification', 'verification.notice'],
    'approved and unverified' => [StatusEnum::ACTIVE, false, null, false, 'verification', 'verification.notice'],
    'pending and verified' => [StatusEnum::PENDING, true, null, false, 'pending', 'registration.status'],
    'refused and verified' => [StatusEnum::REJECTED, true, null, false, 'rejected', 'registration.status'],
    'approved without shop' => [StatusEnum::ACTIVE, true, null, false, 'setup', 'owner.first-shop'],
    'preparing shop' => [StatusEnum::ACTIVE, true, TenantStatus::PROVISIONING, false, 'preparing', 'registration.status'],
    'prepared awaiting activation' => [StatusEnum::ACTIVE, true, TenantStatus::PROVISIONING, true, 'preparing', 'registration.status'],
    'failed preparation' => [StatusEnum::ACTIVE, true, TenantStatus::PROVISIONING_FAILED, false, 'preparing', 'registration.status'],
    'active without completed preparation' => [StatusEnum::ACTIVE, true, TenantStatus::ACTIVE, false, 'preparing', 'registration.status'],
    'ready and active shop' => [StatusEnum::ACTIVE, true, TenantStatus::ACTIVE, true, 'ready', 'dashboard'],
    'suspended shop' => [StatusEnum::ACTIVE, true, TenantStatus::SUSPENDED, true, 'preparing', 'registration.status'],
    'over quota shop' => [StatusEnum::ACTIVE, true, TenantStatus::OVER_QUOTA, true, 'preparing', 'registration.status'],
]);

test('a destination for a rejected account does not load its refusal history', function (): void {
    $merchant = journeyMerchant(['status' => StatusEnum::REJECTED]);
    $journey = app(RegistrationJourney::class);
    $connection = $merchant->getConnection();
    $connection->enableQueryLog();
    try {
        expect($journey->destination($merchant))->toBe(route('registration.status'));
        $destinationQueries = $connection->getQueryLog();
        $connection->flushQueryLog();
        expect($journey->progress($merchant)['state'])->toBe('rejected');
        $progressQueries = $connection->getQueryLog();
    } finally {
        $connection->disableQueryLog();
        $connection->flushQueryLog();
    }

    expect(collect($destinationQueries)->contains(fn (array $query): bool => str_contains($query['query'], 'activity_log')))->toBeFalse()
        ->and(collect($progressQueries)->contains(fn (array $query): bool => str_contains($query['query'], 'activity_log')))->toBeTrue();
});

test('a destination with a failed shop does not check retry permissions and loads shops once', function (): void {
    $merchant = journeyMerchant();
    Event::fake([TenantCreated::class]);
    Tenant::factory()->create(['user_id' => $merchant->id, 'status' => TenantStatus::PROVISIONING_FAILED]);
    Event::assertDispatched(TenantCreated::class);
    Gate::partialMock()->shouldReceive('forUser')->never();
    $connection = $merchant->getConnection();
    $connection->enableQueryLog();
    try {
        expect(app(RegistrationJourney::class)->destination($merchant))->toBe(route('registration.status'));
        $queries = $connection->getQueryLog();
    } finally {
        $connection->disableQueryLog();
        $connection->flushQueryLog();
    }

    expect(collect($queries)->filter(fn (array $query): bool => str_contains($query['query'], 'from `tenants`')))->toHaveCount(1);
});

test('progress retains the failed shop payload and only includes shops owned by the account', function (): void {
    $merchant = journeyMerchant();
    $other = journeyMerchant();
    Event::fake([TenantCreated::class]);
    $failed = Tenant::factory()->create(['user_id' => $merchant->id, 'status' => TenantStatus::PROVISIONING_FAILED]);
    Tenant::factory()->create(['user_id' => $other->id, 'status' => TenantStatus::ACTIVE,
        'schema_version' => 'completed-schema', 'provisioned_at' => now()]);
    Event::assertDispatched(TenantCreated::class);

    expect(app(RegistrationJourney::class)->progress($merchant)['shops'][0]['retry_url'])->toBeNull();
    TenantSchemaDeployment::factory()->create([
        'tenant_id' => $failed->id, 'operation' => OperationEnum::PROVISIONING,
        'status' => DeploymentStatus::FAILED,
    ]);

    $progress = app(RegistrationJourney::class)->progress($merchant);
    expect($progress['state'])->toBe('preparing')
        ->and($progress['shops'])->toBe([[
            'name' => $failed->shop_name,
            'status' => $failed->status->label(),
            'prepared' => false,
            'failed' => true,
            'ready' => false,
            'retry_url' => route('owner.shops.retry', $failed),
        ]]);
});

test('pending credentials errors remain generic when the password is wrong', function (): void {
    $merchant = journeyMerchant(['status' => StatusEnum::PENDING]);
    foreach ([$merchant->email, 'unknown-merchant@example.test'] as $email) {
        $this->post(route('login.store'), ['email' => $email, 'password' => 'incorrect'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertGuest('central');
    }
});

test('a limited session observes rejection and subsequent approval without creating a shop', function (): void {
    $this->seed(AuthorizationSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(Role::findByName('root', 'central'));
    $merchant = journeyMerchant(['status' => StatusEnum::PENDING]);
    $merchant->getConnection()->table('sessions')->insert([
        'id' => 'limited-merchant-session', 'user_id' => $merchant->id,
        'payload' => base64_encode(serialize([])), 'last_activity' => now()->timestamp,
    ]);
    $approvals = app(UserApprovalService::class);

    $initial = $this->actingAs($merchant, 'central')->getJson(route('registration.status'))
        ->assertOk()->assertJsonPath('state', 'pending')->json('version');
    $reason = 'Demande à préciser auprès de notre équipe.';
    $approvals->decide($admin, $merchant, false, $reason, $merchant->legal_profile_version ?? 0, $merchant->status);
    $rejected = $this->getJson(route('registration.status'))->assertOk()
        ->assertJsonPath('state', 'rejected')->assertJsonPath('reason', $reason)->json('version');
    expect($rejected)->not->toBe($initial);
    $this->assertDatabaseHas('sessions', ['id' => 'limited-merchant-session', 'user_id' => $merchant->id]);
    $this->assertAuthenticatedAs($merchant, 'central');
    $this->get(route('registration.status'))->assertOk()->assertSee('Inscription refusée')->assertSee($reason);
    $this->getJson(route('central.users.index'))->assertForbidden();
    $this->post(route('owner.first-shop.store'), [])->assertForbidden();

    $refused = $merchant->fresh();
    $approvals->decide($admin, $refused, true, null, $refused->legal_profile_version ?? 0, $refused->status);
    $this->getJson(route('registration.status'))->assertOk()
        ->assertJsonPath('state', 'setup')->assertJsonPath('reason', null)
        ->assertJsonPath('redirect', route('owner.first-shop'));
    $this->get(route('dashboard'))->assertRedirect(route('owner.first-shop'));
    expect(Activity::forSubject($merchant)->where('event', 'central_account_approved')->firstOrFail()->getProperty('previous_status'))
        ->toBe(StatusEnum::REJECTED->value);
    expect(Tenant::count())->toBe(0);
});

test('first shop creation requires its name and remains explicit owned and idempotent', function (): void {
    $this->seed(AuthorizationSeeder::class);
    Queue::fake([ProvisionTenantDatabase::class]);
    $merchant = journeyMerchant();
    $other = User::factory()->create();
    $payload = ['shop_name' => 'Première boutique', 'slug' => 'premiere', 'creation_key' => (string) Str::uuid(), 'user_id' => $other->id];

    $this->actingAs($merchant, 'central')->get(route('owner.first-shop'))->assertOk()->assertSee('Nom de votre boutique');
    $this->post(route('owner.first-shop.store'), ['creation_key' => $payload['creation_key']])->assertSessionHasErrors('shop_name');
    expect(Tenant::count())->toBe(0);
    $this->post(route('owner.first-shop.store'), $payload)->assertSessionHasNoErrors()->assertRedirect(route('registration.status'));
    $shop = Tenant::where('user_id', $merchant->id)->firstOrFail();
    expect($shop->shop_name)->toBe($payload['shop_name']);
    expect(Tenant::where('user_id', $other->id)->count())->toBe(0);
    $this->getJson(route('registration.status'))->assertJsonPath('state', 'preparing');
    $this->get(route('dashboard'))->assertRedirect(route('registration.status'));
    $this->post(route('owner.first-shop.store'), $payload)->assertSessionHasNoErrors();
    $this->post(route('owner.first-shop.store'), [...$payload, 'creation_key' => (string) Str::uuid()])->assertSessionHasErrors('shop_name');
    expect(Tenant::where('user_id', $merchant->id)->count())->toBe(1);
    Queue::assertPushed(ProvisionTenantDatabase::class, 1);
});

test('progress cannot use another merchants ready shop or expose their refusal reason', function (): void {
    $merchant = journeyMerchant();
    $other = User::factory()->create(['status' => StatusEnum::REJECTED]);
    activity()->performedOn($other)->event('central_account_rejected')->withProperties(['reason' => 'PRIVATE-OTHER-REASON'])->log('Refus.');
    Event::fake([TenantCreated::class]);
    Tenant::factory()->create(['user_id' => $other->id, 'status' => TenantStatus::ACTIVE, 'schema_version' => 'complete', 'provisioned_at' => now()]);
    Event::assertDispatched(TenantCreated::class);

    $this->actingAs($merchant, 'central')->getJson(route('registration.status', ['user' => $other->uuid]))
        ->assertOk()->assertJsonPath('state', 'setup')->assertJsonPath('shops', [])->assertDontSee('PRIVATE-OTHER-REASON');
});

test('progress requires a central session', function (): void {
    $this->getJson(route('registration.status'))->assertUnauthorized();
});

test('an unavailable shop domain is blocked before tenant initialization', function (TenantStatus $status, bool $prepared): void {
    $merchant = journeyMerchant();
    Event::fake([TenantCreated::class]);
    $shop = Tenant::factory()->create([
        'user_id' => $merchant->id, 'status' => $status,
        'schema_version' => $prepared ? 'complete' : null,
        'provisioned_at' => $prepared ? now() : null,
    ]);
    Event::assertDispatched(TenantCreated::class);
    $domain = $shop->domains()->create([
        'domain' => $shop->slug.'.'.config('tenancy.saas_base_domain'),
        'type' => 1, 'is_primary' => true,
    ]);
    $domain->forceFill(['verification_status' => 2, 'verified_at' => now()])->save();

    $this->get('http://'.$domain->domain)->assertStatus(503);
    expect(tenancy()->initialized)->toBeFalse();
})->with([
    'prepared but awaiting activation' => [TenantStatus::PROVISIONING, true],
    'failed' => [TenantStatus::PROVISIONING_FAILED, false],
    'suspended' => [TenantStatus::SUSPENDED, true],
    'over quota' => [TenantStatus::OVER_QUOTA, true],
    'active without preparation' => [TenantStatus::ACTIVE, false],
]);
