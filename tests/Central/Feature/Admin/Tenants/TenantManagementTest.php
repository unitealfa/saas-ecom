<?php

use App\Enums\Central\Deployments\StatusEnum as DeploymentStatus;
use App\Enums\Central\Tenants\StatusEnum;
use App\Enums\Central\Users\StatusEnum as UserStatus;
use App\Jobs\Central\ProvisionTenantDatabase;
use App\Jobs\Central\SyncTenantProfile;
use App\Models\Central\Activity;
use App\Models\Central\Role;
use App\Models\Central\Tenant;
use App\Models\Central\TenantSchemaDeployment;
use App\Models\User;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\TenantCreated;

function module05Admin(): User
{
    test()->seed(AuthorizationSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(Role::findByName('root', 'central'));

    return $admin;
}

function module05Actor(string $permission, int $days = 9999): User
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('module05-'.Str::random(8), [$permission => $days]));

    return $actor;
}

/** @param array<string, mixed> $attributes */
function module05Shop(User $owner, array $attributes = []): Tenant
{
    Event::fake([TenantCreated::class]);
    $shop = Tenant::factory()->create(['user_id' => $owner->id, ...$attributes]);
    Event::assertDispatched(TenantCreated::class);

    return $shop;
}

test('unavailable shops retain the public preparation response without opening a database or exposing identifiers', function (): void {
    $owner = User::factory()->create();
    $shop = module05Shop($owner, ['status' => StatusEnum::SUSPENDED]);
    $domain = $shop->domains()->create(['domain' => 'blocked.'.config('tenancy.saas_base_domain'), 'type' => 1,
        'is_primary' => true, 'verification_status' => 2, 'verified_at' => now()]);
    $this->get('http://'.$domain->domain.'/')->assertServiceUnavailable()->assertContent('Cette boutique est en préparation.');
    $this->get('http://'.$domain->domain.'/_dev/database')->assertForbidden();
    $shop->status = StatusEnum::ACTIVE;
    $shop->save();
    $owner->forceFill(['status' => UserStatus::SUSPENDED])->save();
    $this->get('http://'.$domain->domain.'/')->assertServiceUnavailable()->assertContent('Cette boutique est en préparation.');
    expect(tenancy()->initialized)->toBeFalse()
        ->and($shop->database()->manager()->databaseExists($shop->database()->getName()))->toBeFalse();
});

test('each tenant permission opens consultation and only its own administrative action', function (): void {
    module05Admin();
    $owner = User::factory()->create();
    $shop = module05Shop($owner, ['status' => StatusEnum::INACTIVE]);
    $actions = ['view' => 'view', 'create' => 'create', 'update' => 'update', 'suspend' => 'suspend', 'activate' => 'activate',
        'archive' => 'archive', 'history.view' => 'viewHistory', 'priority.update' => 'updatePriorities'];
    foreach ($actions as $suffix => $ability) {
        $actor = module05Actor('saas.tenants.'.$suffix);
        $gate = Gate::forUser($actor);
        expect($gate->allows('viewAny', Tenant::class))->toBeTrue();
        expect($gate->allows($ability, $ability === 'create' ? [Tenant::class, $owner] : $shop))->toBeTrue();
        foreach (['update', 'suspend', 'activate', 'archive', 'updatePriorities'] as $other) {
            if ($other !== $ability) {
                expect($gate->allows($other, $shop))->toBeFalse();
            }
        }
    }
});

test('tenant readers search shops owners and domains without access to mutation or history', function (): void {
    module05Admin();
    $owner = User::factory()->create(['first_name' => 'Nour', 'last_name' => 'Owner']);
    $shop = module05Shop($owner, ['shop_name' => 'Nour Shop', 'slug' => 'nour']);
    $shop->domains()->create(['domain' => 'nour.'.config('tenancy.saas_base_domain'), 'type' => 1,
        'is_primary' => true, 'verification_status' => 2, 'verified_at' => now()]);
    $reader = module05Actor('saas.tenants.view');
    $this->actingAs($reader, 'central')->get(route('central.tenants.index'))->assertOk()->assertSee('Nour Shop')
        ->assertSee($owner->email)->assertDontSee('Créer une boutique');
    foreach (['Nour Shop', $owner->email, 'nour.'.config('tenancy.saas_base_domain')] as $search) {
        $this->get(route('central.tenants.index', ['search' => $search]))->assertOk()
            ->assertViewHas('shops', fn ($shops): bool => $shops->total() === 1);
    }
    $this->get(route('central.tenants.show', $shop))->assertOk()->assertSee($shop->getInternal('db_name'))
        ->assertDontSee($shop->creation_key)->assertDontSee($shop->creation_hash)->assertDontSee($owner->getRawOriginal('password'));
    $this->get(route('central.tenants.edit', $shop))->assertForbidden();
    $this->get(route('central.tenants.history', $shop))->assertForbidden();
    $this->post(route('central.tenants.archive', $shop), ['expected_status' => $shop->status->value])->assertForbidden();
});

test('a delegated creator reserves an idempotent shop under the free quota without user management rights', function (): void {
    module05Admin();
    $actor = module05Actor('saas.tenants.create');
    $owner = User::factory()->create();
    Queue::fake([ProvisionTenantDatabase::class]);
    $this->actingAs($actor, 'central')->get(route('central.tenants.create'))->assertOk()->assertSee($owner->email)
        ->assertViewHas('owners', fn ($owners): bool => ! $owners->getCollection()->contains('id', $actor->id));
    $this->get(route('central.users.index'))->assertForbidden();
    $payload = ['shop_name' => 'Demo One', 'slug' => 'demo-one', 'creation_key' => (string) Str::uuid()];
    $this->post(route('central.users.tenants.store', $owner), $payload)->assertSessionHasNoErrors();
    $shop = Tenant::where('slug', 'demo-one')->firstOrFail();
    expect($shop->user_id)->toBe($owner->id)->and($shop->status)->toBe(StatusEnum::PROVISIONING);
    expect($shop->getInternal('db_name'))->toBe(config('tenancy.database.prefix').'demo-one');
    expect($shop->domains()->first()->domain)->toBe('demo-one.'.config('tenancy.saas_base_domain'));
    $this->post(route('central.users.tenants.store', $owner), $payload)->assertSessionHasNoErrors();
    Queue::assertPushed(ProvisionTenantDatabase::class, 1);
    $this->post(route('central.users.tenants.store', $owner), ['shop_name' => 'Two', 'slug' => 'demo-two', 'creation_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
    $this->post(route('central.users.tenants.store', $owner), ['shop_name' => 'Three', 'slug' => 'demo-three', 'creation_key' => (string) Str::uuid()])->assertSessionHasErrors('shop_name');
    expect(Tenant::where('user_id', $owner->id)->count())->toBe(2);
    Queue::assertPushed(ProvisionTenantDatabase::class, 2);
});

test('shop creation refuses staff owners and inactive merchant accounts', function (): void {
    $admin = module05Admin();
    $owner = User::factory()->create(['status' => UserStatus::SUSPENDED]);
    $this->actingAs($admin, 'central')->get(route('central.users.tenants.create', $admin))->assertForbidden();
    $this->get(route('central.users.tenants.create', $owner))->assertForbidden();
    $this->post(route('central.users.tenants.store', $owner), ['shop_name' => 'Forbidden', 'slug' => 'forbidden', 'creation_key' => (string) Str::uuid()])->assertForbidden();
    expect(Tenant::count())->toBe(0);
});

test('duplicate slugs and domains return a validation error without reserving another shop or job', function (): void {
    $admin = module05Admin();
    $owner = User::factory()->create();
    $existing = module05Shop($owner, ['slug' => 'reserved']);
    $existing->domains()->create(['domain' => 'free-slug.'.config('tenancy.saas_base_domain'), 'type' => 1, 'is_primary' => true]);
    Queue::fake([ProvisionTenantDatabase::class]);
    foreach (['reserved', 'free-slug'] as $slug) {
        $this->actingAs($admin, 'central')->post(route('central.users.tenants.store', $owner), [
            'shop_name' => 'Nouvelle boutique', 'slug' => $slug, 'creation_key' => (string) Str::uuid(),
        ])->assertSessionHasErrors('slug');
        expect(Tenant::where('user_id', $owner->id)->count())->toBe(1);
    }
    Queue::assertNothingPushed();
});

test('shop renaming retains routing database and ownership while queuing a versioned projection and audit', function (): void {
    module05Admin();
    $owner = User::factory()->create();
    $shop = module05Shop($owner);
    $actor = module05Actor('saas.tenants.update');
    $before = $shop->fresh()->only(['id', 'uuid', 'user_id', 'slug', 'data', 'document_prefix', 'creation_key', 'creation_hash']);
    Queue::fake([SyncTenantProfile::class]);
    $this->actingAs($actor, 'central')->get(route('central.tenants.edit', $shop))->assertOk();
    $this->put(route('central.tenants.update', $shop), ['shop_name' => 'New Name', 'internal_label' => 'Internal', 'expected_profile_version' => 1])
        ->assertSessionHasNoErrors()->assertRedirect(route('central.tenants.show', $shop));
    expect($shop->fresh()->only(array_keys($before)))->toBe($before);
    expect($shop->fresh()->profile_version)->toBe(2)->and($shop->fresh()->shop_name)->toBe('New Name');
    Queue::assertPushed(SyncTenantProfile::class, fn ($job): bool => $job->tenantId === $shop->id && $job->tenantUuid === $shop->uuid);
    expect(Activity::forSubject($shop)->where('event', 'tenant_updated')->exists())->toBeTrue();
    $this->put(route('central.tenants.update', $shop), ['shop_name' => 'Stale', 'internal_label' => 'Old', 'expected_profile_version' => 1])
        ->assertSessionHasErrors('tenant');
    expect($shop->fresh()->shop_name)->toBe('New Name');
    $this->get(route('central.tenants.history', $shop))->assertForbidden();
});

test('tenant edit requests cannot replace technical identities status or owner', function (): void {
    $admin = module05Admin();
    $owner = User::factory()->create();
    $shop = module05Shop($owner);
    $before = $shop->fresh()->getAttributes();
    $this->actingAs($admin, 'central')->put(route('central.tenants.update', $shop), [
        'shop_name' => 'Forged', 'internal_label' => 'Forged', 'expected_profile_version' => 1,
        'slug' => 'fake', 'user_id' => $admin->id, 'data' => ['tenancy_db_name' => 'fake'], 'status' => 2, 'profile_version' => 99,
    ])->assertSessionHasErrors(['slug', 'user_id', 'data', 'status', 'profile_version']);
    expect($shop->fresh()->getAttributes())->toBe($before);
});

test('suspension and archival retain domains and history and cancel pending preparation safely', function (): void {
    $admin = module05Admin();
    $owner = User::factory()->create();
    $shop = module05Shop($owner, ['schema_version' => 'prepared-fixture', 'provisioned_at' => now(), 'status' => StatusEnum::ACTIVE]);
    $domain = $shop->domains()->create(['domain' => $shop->slug.'.'.config('tenancy.saas_base_domain'), 'type' => 1, 'is_primary' => true]);
    $this->actingAs($admin, 'central')->post(route('central.tenants.suspend', $shop), ['expected_status' => 2, 'reason' => 'Administrative test'])
        ->assertSessionHasNoErrors();
    expect($shop->fresh()->status)->toBe(StatusEnum::SUSPENDED);
    $this->post(route('central.tenants.suspend', $shop), ['expected_status' => 2])->assertSessionHasErrors('tenant');
    $pending = TenantSchemaDeployment::factory()->create(['tenant_id' => $shop->id, 'status' => DeploymentStatus::PENDING]);
    $this->post(route('central.tenants.archive', $shop), ['expected_status' => 4])->assertSessionHasNoErrors();
    $archived = Tenant::withTrashed()->findOrFail($shop->id);
    expect($archived->trashed())->toBeTrue()->and($archived->status)->toBe(StatusEnum::DELETED);
    expect($pending->fresh()->status)->toBe(DeploymentStatus::CANCELLED);
    $this->assertDatabaseHas('domains', ['id' => $domain->id, 'tenant_id' => $shop->id]);
    $this->get(route('central.tenants.show', $shop))->assertOk()->assertSee('Archivée');
    $this->get(route('central.tenants.history', $shop))->assertOk()->assertSee('État administratif');
    $this->get(route('central.tenants.index', ['status' => 9]))->assertOk()->assertSee($shop->shop_name);
});

test('archival cannot race with a running deployment', function (): void {
    $admin = module05Admin();
    $shop = module05Shop(User::factory()->create());
    TenantSchemaDeployment::factory()->create(['tenant_id' => $shop->id, 'status' => DeploymentStatus::RUNNING, 'started_at' => now()]);
    $this->actingAs($admin, 'central')->post(route('central.tenants.archive', $shop), ['expected_status' => 1])->assertSessionHasErrors('tenant');
    expect($shop->fresh()->trashed())->toBeFalse();
});

test('initial provisioning cannot be replaced by a manual administrative activation', function (): void {
    $admin = module05Admin();
    $owner = User::factory()->create();
    $shop = module05Shop($owner);
    $this->actingAs($admin, 'central')->post(route('central.tenants.activate', $shop), ['expected_status' => 1])->assertForbidden();
    expect($shop->fresh()->status)->toBe(StatusEnum::PROVISIONING);
    $owner->forceFill(['legal_profile_version' => 1, 'legal_verification_status' => 2, 'legal_verified_at' => now(), 'legal_verified_by_id' => $admin->id])->save();
    $this->post(route('central.tenants.activate', $shop), ['expected_status' => 1])->assertForbidden();
    expect($shop->fresh()->status)->toBe(StatusEnum::PROVISIONING);
});

test('priorities reject duplicate ranks incomplete sets and another owner’s shop', function (): void {
    $admin = module05Admin();
    $owner = User::factory()->create();
    $first = module05Shop($owner);
    $second = module05Shop($owner);
    $foreign = module05Shop(User::factory()->create());
    $this->actingAs($admin, 'central')->put(route('central.tenants.priorities', $first),
        ['priorities' => [$first->uuid => 1, $second->uuid => 1]])->assertSessionHasErrors();
    $this->put(route('central.tenants.priorities', $first), ['priorities' => [$first->uuid => 1]])->assertSessionHasErrors('priorities');
    $this->put(route('central.tenants.priorities', $first), ['priorities' => [$first->uuid => 1, $foreign->uuid => 2]])->assertSessionHasErrors('priorities');
    expect($first->fresh()->activation_priority)->toBeNull()->and($second->fresh()->activation_priority)->toBeNull();
});

test('expired rights guests and owners without SaaS roles cannot administer boutiques', function (): void {
    $this->freezeTime();
    module05Admin();
    $owner = User::factory()->create();
    $shop = module05Shop($owner);
    $this->get(route('central.tenants.index'))->assertRedirect(route('login'));
    $this->actingAs($owner, 'central')->get(route('central.tenants.index'))->assertForbidden();
    $actor = module05Actor('saas.tenants.update', 1);
    $this->travel(1)->days();
    $this->actingAs($actor, 'central')->get(route('central.tenants.index'))->assertForbidden();
    $this->put(route('central.tenants.update', $shop), ['shop_name' => 'Too late', 'internal_label' => 'No', 'expected_profile_version' => 1])->assertForbidden();
    expect($shop->fresh()->profile_version)->toBe(1);
});

test('history consultation is available independently and search treats SQL and wildcard text as data', function (): void {
    module05Admin();
    $shop = module05Shop(User::factory()->create());
    $actor = module05Actor('saas.tenants.history.view');
    $this->actingAs($actor, 'central')->get(route('central.tenants.history', $shop))->assertOk()->assertSee('Historique de');
    foreach (['%', "' OR 1=1 --"] as $search) {
        $this->get(route('central.tenants.index', ['search' => $search]))->assertOk()
            ->assertViewHas('shops', fn ($shops): bool => $shops->total() === 0);
    }
    $this->post(route('central.tenants.suspend', $shop), ['expected_status' => 1])->assertForbidden();
});
