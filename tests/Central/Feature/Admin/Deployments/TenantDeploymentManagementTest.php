<?php

use App\Enums\Central\Deployments\OperationEnum;
use App\Enums\Central\Deployments\StatusEnum;
use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Enums\VerificationStatusEnum;
use App\Jobs\Central\ProvisionTenantDatabase;
use App\Models\Central\Activity;
use App\Models\Central\Permission;
use App\Models\Central\Role;
use App\Models\Central\Tenant;
use App\Models\Central\TenantSchemaDeployment;
use App\Models\User;
use App\Services\Central\Tenants\TenantDeploymentService;
use App\Services\Central\Tenants\TenantSchemaVersions;
use App\Services\Central\Tenants\TenantStatusService;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\DeploymentPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\TenantCreated;

function deploymentAdmin(): User
{
    test()->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create();
    $actor->assignRole(Role::findByName('root', 'central'));

    return $actor;
}

function deploymentActor(string $permission): User
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('deploy-'.Str::random(10), [$permission => 7]));

    return $actor;
}

function deploymentRecord(StatusEnum $status = StatusEnum::PENDING): TenantSchemaDeployment
{
    Event::fake([TenantCreated::class]);
    $tenant = Tenant::factory()->create();

    return TenantSchemaDeployment::factory()->create([
        'tenant_id' => $tenant->id, 'target_version' => app(TenantSchemaVersions::class)->latest(),
        'status' => $status, 'ended_at' => $status === StatusEnum::FAILED ? now() : null,
        'error_code' => $status === StatusEnum::FAILED ? 'PROVISIONING_FAILED' : null,
        'sanitized_error' => $status === StatusEnum::FAILED ? 'private-database-secret' : null,
    ]);
}

test('deployment readers see the paginated history and UUID pages without private diagnostics', function (): void {
    deploymentAdmin();
    $deployment = deploymentRecord(StatusEnum::FAILED);
    $reader = deploymentActor('saas.deployments.view');
    $this->actingAs($reader, 'central')->get(route('central.deployments.index'))
        ->assertOk()->assertSee($deployment->tenant->shop_name)->assertSee('Échec')
        ->assertDontSee('private-database-secret');
    $this->get(route('central.deployments.show', $deployment))->assertOk()
        ->assertSee('Historique des tentatives')->assertDontSee('Diagnostic nettoyé')
        ->assertDontSee('Relancer une tentative')->assertDontSee('private-database-secret');
    $this->get(route('central.deployments.show', $deployment->id))->assertNotFound();
});

test('even authorized error readers never receive stored SQL secrets or raw traces', function (): void {
    $admin = deploymentAdmin();
    $deployment = deploymentRecord(StatusEnum::FAILED);
    $this->actingAs($admin, 'central')->get(route('central.deployments.show', $deployment))
        ->assertOk()->assertSee('Diagnostic nettoyé')->assertDontSee('private-database-secret');
});

test('all deployment HTTP entry points reject staff lacking deployment permissions', function (): void {
    deploymentAdmin();
    $deployment = deploymentRecord();
    $actor = deploymentActor('saas.users.view');
    Queue::fake();
    $this->actingAs($actor, 'central')->get(route('central.deployments.index'))->assertForbidden();
    $this->get(route('central.deployments.show', $deployment))->assertForbidden();
    foreach (['provision', 'migrate', 'retry'] as $action) {
        $this->post(route('central.deployments.'.$action, $deployment), [])->assertForbidden();
    }
    Queue::assertNothingPushed();
});

test('deployment capabilities remain independent', function (string $permission, string $ability): void {
    deploymentAdmin();
    $pending = deploymentRecord();
    $failed = deploymentRecord(StatusEnum::FAILED);
    $actor = deploymentActor($permission);
    $subjects = ['viewAny' => TenantSchemaDeployment::class, 'provision' => $pending,
        'retry' => $failed, 'viewErrors' => $failed];
    foreach ($subjects as $name => $subject) {
        expect(Gate::forUser($actor)->allows($name, $subject))->toBe($name === $ability);
    }
})->with([
    ['saas.deployments.view', 'viewAny'], ['saas.deployments.provision', 'provision'],
    ['saas.deployments.retry', 'retry'], ['saas.deployments.errors.view', 'viewErrors'],
    ['saas.deployments.migrate', 'migrate'],
]);

test('provision requests preserve the existing reservation and use the central job', function (): void {
    deploymentAdmin();
    $deployment = deploymentRecord();
    $actor = deploymentActor('saas.deployments.provision');
    Queue::fake();
    $this->actingAs($actor, 'central')->post(route('central.deployments.provision', $deployment),
        ['expected_state' => $deployment->managementState()])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    Queue::assertPushed(ProvisionTenantDatabase::class, fn ($job) => $job->deploymentId === $deployment->id && $job->connection === 'central-provisioning');
    expect(TenantSchemaDeployment::count())->toBe(1);
    expect(Activity::forSubject($deployment)->where('event', 'tenant_deployment_dispatched')->count())->toBe(1);
});

test('stale deployment forms cannot queue an operation', function (): void {
    $admin = deploymentAdmin();
    $deployment = deploymentRecord();
    $expected = $deployment->managementState();
    $deployment->update(['error_code' => 'changed']);
    Queue::fake();
    $this->actingAs($admin, 'central')->post(route('central.deployments.provision', $deployment), ['expected_state' => $expected])
        ->assertSessionHasErrors('deployment');
    Queue::assertNothingPushed();
});

test('retry preserves the failed row and repeated submissions reuse one new attempt', function (): void {
    $admin = deploymentAdmin();
    $failed = deploymentRecord(StatusEnum::FAILED);
    $failed->tenant->forceFill(['status' => TenantStatus::PROVISIONING_FAILED])->save();
    Queue::fake();
    $payload = ['expected_state' => $failed->managementState()];
    $this->actingAs($admin, 'central')->post(route('central.deployments.retry', $failed), $payload)->assertSessionHasNoErrors();
    $this->post(route('central.deployments.retry', $failed), $payload)->assertSessionHasNoErrors();
    $next = TenantSchemaDeployment::latest('id')->firstOrFail();
    expect($failed->refresh()->status)->toBe(StatusEnum::FAILED);
    expect($failed->sanitized_error)->toBe('private-database-secret');
    expect($next->status)->toBe(StatusEnum::PENDING);
    expect($next->attempt_number)->toBe(2);
    expect($next->correlation_id)->toBe($failed->correlation_id);
    expect(TenantSchemaDeployment::count())->toBe(2);
    expect($failed->tenant->refresh()->status)->toBe(TenantStatus::PROVISIONING);
    Queue::assertPushed(ProvisionTenantDatabase::class, 1);
});

test('archived shops and nonfailed deployments cannot be retried', function (): void {
    $admin = deploymentAdmin();
    $deployment = deploymentRecord();
    Queue::fake();
    $this->actingAs($admin, 'central')->post(route('central.deployments.retry', $deployment),
        ['expected_state' => $deployment->managementState()])->assertForbidden();
    $deployment->update(['status' => StatusEnum::FAILED]);
    $deployment->tenant->delete();
    $this->post(route('central.deployments.retry', $deployment),
        ['expected_state' => $deployment->managementState()])->assertForbidden();
    Queue::assertNothingPushed();
});

test('migration requests reject paths arbitrary versions and malformed expected states', function (): void {
    $admin = deploymentAdmin();
    $deployment = deploymentRecord(StatusEnum::SUCCEEDED);
    $deployment->tenant->forceFill(['status' => TenantStatus::ACTIVE, 'provisioned_at' => now(),
        'schema_version' => pathinfo(app(TenantSchemaVersions::class)->files()[0], PATHINFO_FILENAME)])->save();
    Queue::fake();
    $this->actingAs($admin, 'central')->postJson(route('central.deployments.migrate', $deployment), [
        'expected_state' => $deployment->managementState(), 'target_version' => '../../central', 'path' => 'database/migrations/central',
    ])->assertUnprocessable()->assertJsonValidationErrors(['target_version', 'path']);
    Queue::assertNothingPushed();
    expect(TenantSchemaDeployment::count())->toBe(1);
});

test('a rollback removes both the deployment audit and its durable database job', function (): void {
    $admin = deploymentAdmin();
    $deployment = deploymentRecord();
    $before = DB::table('jobs')->count();
    expect(function () use ($admin, $deployment): void {
        DB::transaction(function () use ($admin, $deployment): void {
            app(TenantDeploymentService::class)->provision($admin, $deployment, $deployment->managementState());
            throw new LogicException('abort-test');
        });
    })->toThrow(LogicException::class, 'abort-test');
    expect(DB::table('jobs')->count())->toBe($before);
    expect(Activity::forSubject($deployment)->where('event', 'tenant_deployment_dispatched')->exists())->toBeFalse();
});

test('an older reservation cannot start after another operation has been requested', function (): void {
    $admin = deploymentAdmin();
    $older = deploymentRecord();
    TenantSchemaDeployment::factory()->create(['tenant_id' => $older->tenant_id,
        'target_version' => $older->target_version, 'status' => StatusEnum::SUCCEEDED]);
    Queue::fake();
    $this->actingAs($admin, 'central')->post(route('central.deployments.provision', $older),
        ['expected_state' => $older->managementState()])->assertSessionHasErrors('deployment');
    Queue::assertNothingPushed();
});

test('deployment filters preserve scope and reject invalid states', function (): void {
    $admin = deploymentAdmin();
    $deployment = deploymentRecord(StatusEnum::FAILED);
    $this->actingAs($admin, 'central')->get(route('central.deployments.index', ['search' => 'absent-shop']))
        ->assertOk()->assertViewHas('deployments', fn ($items) => $items->total() === 0);
    $this->getJson(route('central.deployments.index', ['status' => 42]))->assertUnprocessable();
    $this->get(route('central.deployments.index', ['status' => StatusEnum::FAILED->value]))
        ->assertOk()->assertSee($deployment->tenant->shop_name);
});

test('the deployment-only seeder is idempotent and does not change role assignments', function (): void {
    deploymentAdmin();
    $roles = DB::table('model_has_roles')->get()->toJson();
    $direct = DB::table('model_has_permissions')->get()->toJson();
    $this->seed(DeploymentPermissionSeeder::class);
    $this->seed(DeploymentPermissionSeeder::class);
    expect(Permission::where('name', 'like', 'saas.deployments.%')->count())->toBe(5);
    expect(DB::table('model_has_roles')->get()->toJson())->toBe($roles);
    expect(DB::table('model_has_permissions')->get()->toJson())->toBe($direct);
});

test('a terminal queue failure closes the abandoned automatic attempt without erasing the first failure', function (): void {
    $failed = deploymentRecord(StatusEnum::FAILED);
    $next = app(TenantDeploymentService::class)->reserveRetry($failed);
    $next->update(['status' => StatusEnum::RUNNING, 'started_at' => now()]);
    (new ProvisionTenantDatabase($failed->id))->failed(new LogicException('private-worker-error'));
    expect($next->refresh()->status)->toBe(StatusEnum::FAILED);
    expect($next->error_code)->toBe('DEPLOYMENT_EXHAUSTED');
    expect($failed->refresh()->sanitized_error)->toBe('private-database-secret');
    expect($next->sanitized_error)->not->toContain('private-worker-error');
});

test('migration completion preserves a suspension imposed before completion', function (): void {
    $deployment = deploymentRecord(StatusEnum::RUNNING);
    $deployment->tenant->forceFill(['status' => TenantStatus::SUSPENDED])->save();
    app(TenantStatusService::class)->completeProvisioning($deployment->tenant, $deployment->id);
    expect($deployment->refresh()->status)->toBe(StatusEnum::SUCCEEDED);
    expect($deployment->tenant->refresh()->status)->toBe(TenantStatus::SUSPENDED);
});

test('a running deployment blocks a prepared tenant before its database is initialized', function (): void {
    $deployment = deploymentRecord(StatusEnum::RUNNING);
    $tenant = $deployment->tenant;
    $tenant->forceFill(['status' => TenantStatus::ACTIVE, 'schema_version' => $deployment->target_version, 'provisioned_at' => now()])->save();
    $domain = $tenant->domains()->make(['domain' => 'busy-deployment.'.config('tenancy.saas_base_domain'), 'type' => 1, 'is_primary' => true]);
    $domain->forceFill(['verification_status' => VerificationStatusEnum::VERIFIED, 'verified_at' => now()])->save();
    $this->get('http://'.$domain->domain.'/')->assertServiceUnavailable();
    expect(tenancy()->initialized)->toBeFalse();
});

test('the merchant provisioning retry cannot be used for an administrative migration', function (): void {
    $reviewer = deploymentAdmin();
    $failed = deploymentRecord(StatusEnum::FAILED);
    $owner = $failed->tenant->owner;
    $owner->forceFill(['legal_verification_status' => VerificationStatusEnum::VERIFIED,
        'legal_verified_at' => now(), 'legal_verified_by_id' => $reviewer->id, 'legal_profile_version' => 1])->save();
    expect(Gate::forUser($owner)->allows('retryProvisioning', $failed->tenant))->toBeTrue();
    TenantSchemaDeployment::factory()->create(['tenant_id' => $failed->tenant_id,
        'operation' => OperationEnum::MIGRATION, 'status' => StatusEnum::FAILED]);
    expect(Gate::forUser($owner)->allows('retryProvisioning', $failed->tenant))->toBeFalse();
});
