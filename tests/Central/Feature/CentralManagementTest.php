<?php

use App\Enums\Central\Deployments\StatusEnum as DeploymentStatus;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Jobs\Central\ProvisionTenantDatabase;
use App\Models\Central\Activity;
use App\Models\Central\Country;
use App\Models\Central\Feature;
use App\Models\Central\Permission;
use App\Models\Central\Plan;
use App\Models\Central\Role;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Models\Central\TenantSchemaDeployment;
use App\Models\User;
use App\Notifications\Central\Auth\AccountInvitation;
use App\Services\Central\Plans\FreePlanCatalog;
use App\Services\Central\Subscriptions\DefaultSubscription;
use App\Services\Central\Tenants\TenantProvisioner;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\TenantCreated;

beforeEach(function (): void {
    $this->seed(AuthorizationSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::findByName('root', 'central'));
    Notification::fake();
    Queue::fake();
});

function centralRolePayload(string $name, array $grants): array
{
    $permissions = Permission::whereIn('name', array_keys($grants))->get();

    return [
        'name' => $name, 'label' => $name,
        'permissions' => $permissions->pluck('uuid')->all(),
        'durations' => $permissions->mapWithKeys(fn (Permission $permission): array => [$permission->uuid => $grants[$permission->name]])->all(),
    ];
}

test('the super admin sees and renders all central management forms', function (): void {
    $owner = User::factory()->create();
    $this->actingAs($this->admin, 'central');
    foreach (['central.roles.index', 'central.roles.create', 'central.users.create'] as $name) {
        $this->get(route($name))->assertOk();
    }
    $this->get(route('central.users.roles.edit', $owner))->assertOk();
    $this->get(route('central.users.tenants.create', $owner))->assertOk();
    $this->get(route('central.users.index'))->assertOk()->assertSee('Créer un compte')->assertSee('Boutiques');
});

test('custom roles use Spatie with independent permission durations and reject duplicate names or compositions', function (): void {
    $payload = centralRolePayload('support-limited', ['saas.users.view' => 12, 'saas.users.create' => 9999]);
    $this->actingAs($this->admin, 'central')->post(route('central.roles.store'), $payload)->assertSessionHasNoErrors()->assertRedirect(route('central.roles.index'));
    $role = Role::where('name', 'support-limited')->firstOrFail();
    expect($role)->toBeInstanceOf(Spatie\Permission\Models\Role::class);
    expect($role->permissions()->where('name', 'saas.users.view')->firstOrFail()->pivot->duration_days)->toBe(12);
    $this->post(route('central.roles.store'), [...$payload, 'name' => 'same-composition'])->assertSessionHasErrors('name');
    $this->post(route('central.roles.store'), centralRolePayload('support-limited', ['saas.users.view' => 34]))->assertSessionHasErrors('name');
    $this->post(route('central.roles.store'), centralRolePayload('bad-duration', ['saas.users.view' => 10000]))->assertSessionHasErrors();
    $this->put(route('central.roles.update', Role::findByName('user-manager')), $payload)->assertForbidden();
});

test('changing a role cannot introduce overlap for any existing recipient', function (): void {
    $target = User::factory()->create();
    $first = Role::createWithPermissions('short-reader', ['saas.users.view' => 2]);
    $second = Role::createWithPermissions('short-creator', ['saas.users.create' => 2]);
    $target->assignRole($first, $second);
    $this->actingAs($this->admin, 'central')->put(route('central.roles.update', $first),
        centralRolePayload('short-reader', ['saas.users.view' => 2, 'saas.users.create' => 2]))->assertSessionHasErrors('permissions');
    expect($first->permissions()->pluck('name')->all())->toBe(['saas.users.view']);
});

test('editing a custom role changes the effective duration without resetting its assignment date', function (): void {
    $this->freezeTime();
    $role = Role::createWithPermissions('adjustable-reader', ['saas.users.view' => 1]);
    $target = User::factory()->create();
    $target->assignRole($role);
    $assignedAt = $target->roles()->firstOrFail()->pivot->assigned_at;
    $this->travel(2)->days();
    expect($target->checkPermissionTo('saas.users.view', 'central'))->toBeFalse();
    $this->actingAs($this->admin, 'central')->get(route('central.roles.edit', $role))->assertOk();
    $this->put(route('central.roles.update', $role), centralRolePayload('adjustable-reader', ['saas.users.view' => 7]))->assertSessionHasNoErrors();
    expect($role->fresh()->permission_version)->toBe(2);
    expect($target->roles()->firstOrFail()->pivot->assigned_at)->toEqual($assignedAt);
    expect($target->checkPermissionTo('saas.users.view', 'central'))->toBeTrue();
});

test('staff invitation uses the central password broker and an actual parent country and subscription', function (): void {
    $role = Role::findByName('user-reader');
    $country = Country::where('code', 'DZ')->firstOrFail();
    $this->actingAs($this->admin, 'central')->post(route('central.users.store'), [
        'first_name' => 'Amine', 'last_name' => 'Test', 'email' => ' Invited@Example.test ',
        'country_uuid' => $country->uuid, 'account_kind' => 'staff', 'roles' => [$role->uuid],
    ])->assertSessionHasNoErrors();
    $invited = User::where('email', 'invited@example.test')->firstOrFail();
    expect($invited->country_id)->toBe($country->id);
    expect($invited->email_verified_at)->toBeNull();
    expect($invited->hasRole($role))->toBeTrue();
    $subscription = Subscription::where('user_id', $invited->id)->firstOrFail();
    expect($subscription->plan()->firstOrFail()->code)->toBe('free');
    expect($subscription->plan()->firstOrFail()->features()->firstOrFail()->limit)->toBe(2);
    Notification::assertSentTo($invited, AccountInvitation::class);
    $invitation = Notification::sent($invited, AccountInvitation::class)->first();
    expect($invitation->toMail($invited)->actionUrl)->toStartWith(config('app.url'));
    expect(Activity::where('event', 'account_invited')->firstOrFail()->properties->toJson())->not->toContain($invitation->token);
    $this->post(route('logout'));
    $this->post(route('password.update'), [
        'token' => $invitation->token, 'email' => $invited->email,
        'password' => 'PrivatePassword!2026', 'password_confirmation' => 'PrivatePassword!2026',
    ])->assertSessionHasNoErrors();
    expect(Hash::check('PrivatePassword!2026', $invited->fresh()->password))->toBeTrue();
});

test('merchant creation cannot smuggle SaaS roles or a chosen password', function (): void {
    $payload = [
        'last_name' => 'Merchant', 'email' => 'merchant@example.test',
        'country_uuid' => Country::where('code', 'DZ')->firstOrFail()->uuid, 'account_kind' => 'merchant',
    ];
    $this->actingAs($this->admin, 'central')->post(route('central.users.store'), [...$payload, 'roles' => [Role::findByName('user-reader')->uuid]])->assertSessionHasErrors('roles');
    expect(User::where('email', $payload['email'])->exists())->toBeFalse();
    $this->post(route('central.users.store'), [...$payload, 'password' => 'injected'])->assertSessionHasErrors('password');
    $this->post(route('central.users.store'), $payload)->assertSessionHasNoErrors();
    expect(User::where('email', $payload['email'])->firstOrFail()->roles()->exists())->toBeFalse();
});

test('malformed text fields return validation errors without creating records', function (string $form): void {
    $arguments = $form === 'shop' ? [User::factory()->create()] : [];
    [$route, $payload, $fields, $model] = match ($form) {
        'account' => ['central.users.store', [
            'last_name' => ['invalid'], 'first_name' => ['invalid'], 'email' => ['invalid'],
            'country_uuid' => Country::where('code', 'DZ')->firstOrFail()->uuid, 'account_kind' => 'merchant',
        ], ['last_name', 'first_name', 'email'], User::class],
        'role' => ['central.roles.store', [
            ...centralRolePayload('invalid', ['saas.users.view' => 7]),
            'name' => ['invalid'], 'label' => ['invalid'],
        ], ['name', 'label'], Role::class],
        'shop' => ['central.users.tenants.store', [
            'shop_name' => ['invalid'], 'slug' => ['invalid'], 'creation_key' => (string) Str::uuid(),
        ], ['shop_name', 'slug'], Tenant::class],
    };
    $count = $model::count();
    $this->actingAs($this->admin, 'central')->postJson(route($route, $arguments), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($fields);
    expect($model::count())->toBe($count);
    Notification::assertNothingSent();
    Queue::assertNothingPushed();
})->with(['account', 'role', 'shop']);

test('creation and assignment remain usable after the separate viewing permission expires', function (): void {
    $this->freezeTime();
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('creator-with-expiring-view', [
        'saas.users.view' => 1, 'saas.users.create' => 9999,
        'saas.staff.invite' => 9999, 'saas.users.roles.assign' => 9999,
    ]));
    $grant = Role::createWithPermissions('merchant-creator', ['saas.users.create' => 9999]);
    $this->travel(2)->days();
    $this->actingAs($actor, 'central')->get(route('central.users.index'))->assertForbidden();
    $this->get(route('dashboard'))->assertOk()->assertSee('Créer un compte')->assertDontSee('Consulter les utilisateurs');
    $this->get(route('central.users.create'))->assertOk()->assertSee('Retour au dashboard');
    $this->post(route('central.users.store'), [
        'last_name' => 'Merchant', 'email' => 'new-merchant@example.test', 'account_kind' => 'merchant',
        'country_uuid' => Country::where('code', 'DZ')->firstOrFail()->uuid,
    ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    $target = User::where('email', 'new-merchant@example.test')->firstOrFail();
    Notification::assertSentTo($target, AccountInvitation::class);
    $this->get(route('dashboard'))->assertOk()->assertSee('Compte créé pour Merchant');
    $this->get(route('central.users.roles.edit', $target))->assertOk()->assertSee('Retour au dashboard');
    $this->put(route('central.users.roles.update', $target), ['roles' => [$grant->uuid]])
        ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    expect($target->hasRole($grant))->toBeTrue();
});

test('delegated staff management is limited to currently owned permissions and never root', function (): void {
    $delegator = User::factory()->create();
    $role = Role::createWithPermissions('delegated-staff', [
        'saas.users.view' => 1, 'saas.staff.invite' => 9999, 'saas.users.roles.assign' => 9999,
    ]);
    $delegator->assignRole($role);
    $target = User::factory()->create();
    $this->actingAs($delegator, 'central')->put(route('central.users.roles.update', $target), [
        'roles' => [Role::findByName('user-reader')->uuid],
    ])->assertSessionHasNoErrors();
    $this->put(route('central.users.roles.update', $target), ['roles' => [Role::findByName('user-manager')->uuid]])->assertForbidden();
    $this->put(route('central.users.roles.update', $target), ['roles' => [Role::findByName('root')->uuid]])->assertSessionHasErrors('roles.0');
    $this->put(route('central.users.roles.update', $this->admin), ['roles' => []])->assertForbidden();
    $this->travel(1)->days();
    $this->put(route('central.users.roles.update', $target), ['roles' => [Role::findByName('user-reader')->uuid]])->assertForbidden();
});

test('Spatie assignments reject shared permissions and keep the original date when kept', function (): void {
    $target = User::factory()->create();
    $role = Role::findByName('user-reader');
    $target->assignRole($role);
    $assignedAt = $target->roles()->firstOrFail()->pivot->assigned_at;
    $this->travel(3)->days();
    $this->actingAs($this->admin, 'central')->put(route('central.users.roles.update', $target), ['roles' => [$role->uuid]])->assertSessionHasNoErrors();
    expect($target->roles()->firstOrFail()->pivot->assigned_at)->toEqual($assignedAt);
    $this->put(route('central.users.roles.update', $target), ['roles' => [$role->uuid, Role::findByName('user-manager')->uuid]])->assertSessionHasErrors('roles');
    expect($target->roles()->pluck('name')->all())->toBe(['user-reader']);
});

test('reader and unverified users cannot access management actions by URL', function (): void {
    $reader = User::factory()->create();
    $reader->assignRole(Role::findByName('user-reader'));
    $this->actingAs($reader, 'central')->get(route('central.users.create'))->assertForbidden();
    $this->get(route('central.roles.index'))->assertForbidden();
    $this->post(route('central.roles.store'), centralRolePayload('forbidden', ['saas.users.view' => 4]))->assertForbidden();
    $unverified = User::factory()->unverified()->create();
    $this->actingAs($unverified, 'central')->get(route('central.users.create'))->assertRedirect(route('verification.notice'));
});

test('a protected Super Admin assignment cannot be replaced even before its start date', function (): void {
    $target = User::factory()->create();
    $target->assignRole(Role::findByName('root'));
    $target->roles()->updateExistingPivot(Role::findByName('root')->id, ['assigned_at' => now()->addDay()]);
    $this->actingAs($this->admin, 'central')->put(route('central.users.roles.update', $target), [
        'roles' => [Role::findByName('user-reader')->uuid],
    ])->assertForbidden();
    expect($target->hasRole('root'))->toBeTrue();
});

test('shop reservation enforces two places and idempotency and records a durable provisioning job', function (): void {
    $owner = User::factory()->create();
    $payload = ['shop_name' => 'Nour', 'slug' => 'nour', 'creation_key' => (string) Str::uuid()];
    $this->actingAs($this->admin, 'central')->post(route('central.users.tenants.store', $owner), $payload)->assertSessionHasNoErrors();
    $tenant = Tenant::where('slug', 'nour')->firstOrFail();
    expect($tenant->user_id)->toBe($owner->id);
    expect($tenant->domains()->firstOrFail()->domain)->toBe('nour.'.config('tenancy.saas_base_domain'));
    expect($tenant->getInternal('db_name'))->toBe(config('tenancy.database.prefix').'nour');
    expect(TenantSchemaDeployment::where('tenant_id', $tenant->id)->firstOrFail()->status)->toBe(DeploymentStatus::PENDING);
    Queue::assertPushed(ProvisionTenantDatabase::class, 1);
    $this->post(route('central.users.tenants.store', $owner), $payload)->assertSessionHasNoErrors();
    expect(Tenant::where('user_id', $owner->id)->count())->toBe(1);
    Queue::assertPushed(ProvisionTenantDatabase::class, 1);
    $this->post(route('central.users.tenants.store', $owner), [...$payload, 'shop_name' => 'Changed'])->assertSessionHasErrors('creation_key');
    $this->post(route('central.users.tenants.store', $owner), [
        'shop_name' => 'Second', 'slug' => 'second', 'creation_key' => (string) Str::uuid(),
    ])->assertSessionHasNoErrors();
    $this->post(route('central.users.tenants.store', $owner), [
        'shop_name' => 'Third', 'slug' => 'third', 'creation_key' => (string) Str::uuid(),
    ])->assertSessionHasErrors('shop_name');
    expect(Tenant::where('user_id', $owner->id)->count())->toBe(2);
    expect(TenantSchemaDeployment::count())->toBe(2);
});

test('the default free plan is idempotent and preserves existing paid subscriptions', function (): void {
    $owner = User::factory()->create();
    $first = app(DefaultSubscription::class)->ensure($owner);
    expect(app(DefaultSubscription::class)->ensure($owner)->id)->toBe($first->id);
    expect(app(FreePlanCatalog::class)->current()->id)->toBe($first->plan_id);
    $other = User::factory()->create();
    $paid = Subscription::factory()->create(['user_id' => $other->id]);
    expect(app(DefaultSubscription::class)->ensure($other)->id)->toBe($paid->id);
    expect(Plan::where('code', 'free')->count())->toBe(1);
});

test('expired subscriptions fall back to the new free version without rewriting a used offer', function (): void {
    $owner = User::factory()->create();
    $feature = Feature::factory()->create(['code' => 'shops']);
    $old = Plan::factory()->create(['code' => 'free']);
    $old->features()->create(['feature_id' => $feature->id, 'is_active' => true, 'limit' => 1]);
    $expired = Subscription::factory()->create([
        'user_id' => $owner->id, 'plan_id' => $old->id,
        'started_at' => now()->subDays(3), 'period_starts_at' => now()->subDays(3), 'period_ends_at' => now()->subDay(),
    ]);
    $current = app(DefaultSubscription::class)->ensure($owner);
    expect($expired->fresh()->status)->toBe(StatusEnum::EXPIRED);
    expect($current->plan()->firstOrFail()->version)->toBe(2);
    expect($current->plan()->firstOrFail()->features()->firstOrFail()->limit)->toBe(2);
    expect($old->features()->firstOrFail()->limit)->toBe(1);
});

test('an administrator can delegate an owned role globally without target lists', function (): void {
    $role = Role::findByName('user-reader');
    $target = User::factory()->create();
    $this->actingAs($this->admin, 'central')->put(route('central.users.roles.update', $target), ['roles' => [$role->uuid]])->assertSessionHasNoErrors();
    expect($target->fresh()->hasRole($role))->toBeTrue();
});
test('provisioning failures retain the reservation and expose only a sanitized error for recovery', function (): void {
    Event::fake([TenantCreated::class]);
    $tenant = Tenant::factory()->create(['user_id' => $this->admin->id]);
    $deployment = TenantSchemaDeployment::factory()->create(['tenant_id' => $tenant->id]);
    $provisioner = Mockery::mock(TenantProvisioner::class);
    $provisioner->shouldReceive('provision')->once()->andThrow(new LogicException('private-database-secret'));
    $job = new ProvisionTenantDatabase($deployment->id);
    expect(fn () => $job->handle($provisioner))->toThrow(LogicException::class);
    expect($deployment->fresh()->status)->toBe(DeploymentStatus::FAILED);
    expect($deployment->fresh()->sanitized_error)->not->toContain('private-database-secret');
    expect(Tenant::whereKey($tenant->id)->exists())->toBeTrue();
    $this->artisan('aydra:retry-tenant-provisioning')->assertSuccessful();
    Queue::assertPushed(ProvisionTenantDatabase::class, 1);
});
