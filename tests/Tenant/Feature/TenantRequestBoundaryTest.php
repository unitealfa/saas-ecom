<?php

use App\Enums\Central\Domains\CertificateStatusEnum;
use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Enums\Central\Users\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Http\Middleware\Tenant\EnsureTenantAvailable;
use App\Http\Middleware\Tenant\FinishTenantRequest;
use App\Models\Central\Tenant;
use App\Models\User;
use Database\Factories\Central\DomainFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Stancl\Tenancy\Events\TenantCreated;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('tenant availability enforces domain ownership status and completed provisioning', function (bool $verified, StatusEnum $ownerStatus, TenantStatus $status, bool $prepared, string $routeName, int $expected): void {
    $owner = User::factory()->create(['status' => $ownerStatus]);
    Event::fake([TenantCreated::class]);
    $tenant = Tenant::factory()->create(['user_id' => $owner->id, 'status' => $status,
        'schema_version' => $prepared ? 'completed-schema' : null, 'provisioned_at' => $prepared ? now() : null]);
    DomainFactory::new()->create(['uuid' => (string) Str::uuid(), 'tenant_id' => $tenant->id,
        'domain' => 'boundary.aydra.localhost', 'type' => 1, 'is_primary' => true,
        'verification_status' => $verified ? VerificationStatusEnum::VERIFIED : VerificationStatusEnum::PENDING,
        'verified_at' => $verified ? now() : null]);
    $request = Request::create('http://boundary.aydra.localhost/');
    $request->setRouteResolver(fn () => (new Route('GET', '/', []))->name($routeName));

    try {
        $response = app(EnsureTenantAvailable::class)->handle($request, fn () => response('available'));
    } catch (HttpExceptionInterface $exception) {
        $response = response('', $exception->getStatusCode());
    }

    TestResponse::fromBaseResponse($response)->assertStatus($expected);
    expect(tenancy()->initialized)->toBeFalse();
    Event::assertDispatchedTimes(TenantCreated::class, 1);
})->with([
    'unverified domain' => [false, StatusEnum::ACTIVE, TenantStatus::ACTIVE, true, 'tenant.home', 404],
    'suspended owner private endpoint' => [true, StatusEnum::SUSPENDED, TenantStatus::ACTIVE, true, 'tenant.database-diagnostics', 403],
    'suspended shop private endpoint' => [true, StatusEnum::ACTIVE, TenantStatus::SUSPENDED, true, 'tenant.database-diagnostics', 403],
    'preparing public shop' => [true, StatusEnum::ACTIVE, TenantStatus::PROVISIONING, false, 'tenant.home', 503],
    'active shop missing preparation' => [true, StatusEnum::ACTIVE, TenantStatus::ACTIVE, false, 'tenant.home', 503],
    'ready shop reaches the next handler' => [true, StatusEnum::ACTIVE, TenantStatus::ACTIVE, true, 'tenant.home', 200],
]);

test('unknown shop domains return 404 before invoking the next handler', function (): void {
    expect(fn () => app(EnsureTenantAvailable::class)->handle(
        Request::create('http://missing.aydra.localhost/'),
        fn () => response('should not run'),
    ))->toThrow(NotFoundHttpException::class);
});

test('tenant requests restore the central connection after success or an exception', function (bool $fail): void {
    Event::fake([TenantCreated::class]);
    $tenant = Tenant::factory()->create();
    $database = $tenant->database();
    $database->manager()->createDatabase($tenant);
    $central = DB::getDefaultConnection();

    try {
        $response = app(FinishTenantRequest::class)->handle(Request::create('/'), function () use ($tenant, $fail) {
            tenancy()->initialize($tenant);
            expect(DB::getDefaultConnection())->toBe('tenant');
            expect(DB::connection()->getDatabaseName())->toBe($tenant->database()->getName());
            if ($fail) {
                throw new RuntimeException('downstream failure');
            }

            return response('completed');
        });
        expect($fail)->toBeFalse();
        expect($response->getContent())->toBe('completed');
    } catch (RuntimeException $exception) {
        expect($fail)->toBeTrue();
        expect($exception->getMessage())->toBe('downstream failure');
    } finally {
        $initializedAfterRequest = tenancy()->initialized;
        $connectionAfterRequest = DB::getDefaultConnection();
        try {
            tenancy()->end();
        } finally {
            TestCase::dropIsolatedMysqlDatabase($database->getName());
        }
    }

    expect($initializedAfterRequest)->toBeFalse();
    expect($connectionAfterRequest)->toBe($central);
    Event::assertDispatchedTimes(TenantCreated::class, 1);
})->with(['normal response' => false, 'downstream exception' => true]);

test('ready shop public aliases redirect only to their verified primary and always end tenancy', function (): void {
    $owner = User::factory()->create();
    Event::fake([TenantCreated::class]);
    $otherTenant = Tenant::factory()->create();
    DomainFactory::new()->create(['tenant_id' => $otherTenant->id, 'domain' => 'foreign-primary.example.com',
        'type' => 2, 'is_primary' => true, 'verification_status' => VerificationStatusEnum::VERIFIED,
        'verified_at' => now(), 'certificate_status' => CertificateStatusEnum::ACTIVE]);
    $tenant = Tenant::factory()->create(['user_id' => $owner->id, 'status' => TenantStatus::ACTIVE,
        'schema_version' => 'prepared-schema', 'provisioned_at' => now()]);
    $alias = DomainFactory::new()->create(['tenant_id' => $tenant->id, 'domain' => 'public-alias.aydra.localhost',
        'type' => 1, 'verification_status' => VerificationStatusEnum::VERIFIED, 'verified_at' => now()]);
    $primary = DomainFactory::new()->create(['tenant_id' => $tenant->id, 'domain' => 'public-primary.example.com',
        'type' => 2, 'is_primary' => true, 'verification_status' => VerificationStatusEnum::VERIFIED,
        'verified_at' => now(), 'certificate_status' => CertificateStatusEnum::ACTIVE]);
    $database = $tenant->database();
    $database->manager()->createDatabase($tenant);
    try {
        $this->get('http://'.$alias->domain.'/')->assertRedirect('https://'.$primary->domain.'/')
            ->assertHeader('Cache-Control', 'no-store, private');
        expect(tenancy()->initialized)->toBeFalse()->and(DB::getDefaultConnection())->toBe('mysql');
        $this->head('http://'.$alias->domain.'/')->assertRedirect('https://'.$primary->domain.'/');
        $this->get('https://'.$primary->domain.'/')->assertServiceUnavailable();
        $primary->forceFill(['certificate_status' => CertificateStatusEnum::ERROR])->save();
        $this->get('http://'.$alias->domain.'/')->assertServiceUnavailable();
        expect(tenancy()->initialized)->toBeFalse();
    } finally {
        tenancy()->end();
        TestCase::dropIsolatedMysqlDatabase($database->getName());
    }
    Event::assertDispatchedTimes(TenantCreated::class, 2);
});
