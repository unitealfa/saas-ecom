<?php

use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Enums\Central\Users\StatusEnum;
use App\Jobs\Central\ProvisionTenantDatabase;
use App\Models\Central\Activity;
use App\Models\Central\Role;
use App\Models\Central\Tenant;
use App\Models\User;
use App\Services\Central\Users\UserApprovalService;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\TenantCreated;

function journeyMerchant(array $attributes = []): User
{
    if (($attributes['status'] ?? StatusEnum::ACTIVE) === StatusEnum::ACTIVE) {
        $validator = User::factory()->create();
        $attributes += ['legal_profile_version' => 1, 'legal_verified_by_id' => $validator->id,
            'legal_verification_status' => \App\Enums\VerificationStatusEnum::VERIFIED, 'legal_verified_at' => now()];
    }
    return User::factory()->create($attributes);
}

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
    $this->getJson(route('registration.status'))->assertOk()
        ->assertJsonPath('state', $state)->assertJsonPath('redirect', route($destination))
        ->assertJsonMissingPath('user_id')->assertJsonMissingPath('password');
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
        'type' => 1, 'is_primary' => true, 'verification_status' => 2, 'verified_at' => now(),
    ]);

    $this->get('http://'.$domain->domain)->assertStatus(503);
    expect(tenancy()->initialized)->toBeFalse();
})->with([
    'prepared but awaiting activation' => [TenantStatus::PROVISIONING, true],
    'failed' => [TenantStatus::PROVISIONING_FAILED, false],
    'suspended' => [TenantStatus::SUSPENDED, true],
    'over quota' => [TenantStatus::OVER_QUOTA, true],
    'active without preparation' => [TenantStatus::ACTIVE, false],
]);
