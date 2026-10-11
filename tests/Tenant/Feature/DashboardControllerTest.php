<?php

use App\DTOs\Tenant\DashboardData;
use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Users\StatusEnum;
use App\Http\Controllers\Tenant\DashboardController;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use App\Models\User as CentralUser;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

beforeEach(function (): void {
    config(['tenancy.saas_base_domain' => 'aydra.localhost']);
    Route::middleware('web')->get('/_testing/tenant-dashboard', DashboardController::class);
});

afterEach(function (): void {
    tenancy()->end();

    if (Schema::hasTable('tenants')) {
        foreach (Tenant::withTrashed()->get() as $tenant) {
            TestCase::dropIsolatedMysqlDatabase($tenant->database()->getName());
        }
    }
});

test('the prepared dashboard returns 403 without an initialized boutique', function (): void {
    $user = User::factory()->make(['id' => 1]);

    $this->actingAs($user, 'tenant')
        ->get('/_testing/tenant-dashboard')
        ->assertForbidden();
});

test('a central account cannot open the prepared boutique dashboard and receives 401', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $owner = CentralUser::factory()->create();
    $tenant = Tenant::factory()->create(['user_id' => $owner->id]);
    tenancy()->initialize($tenant);

    $this->actingAs($owner, 'central')
        ->get('/_testing/tenant-dashboard')
        ->assertUnauthorized();
});

test('the prepared dashboard displays the local account and current shop with escaped names', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $tenant = Tenant::factory()->create(['shop_name' => '<img src=x onerror=alert(1)>']);
    tenancy()->initialize($tenant);
    $user = User::factory()->create([
        'first_name' => '<script>alert(1)</script>',
        'last_name' => 'Test',
        'status' => StatusEnum::ACTIVE,
        'membership_status' => MembershipStatusEnum::ACTIVE,
        'joined_at' => now(),
        'email_verified_at' => now(),
    ]);

    $this->withoutVite();
    $this->actingAs($user, 'tenant')
        ->get('/_testing/tenant-dashboard')
        ->assertOk()
        ->assertViewIs('tenant.pages.dashboard')
        ->assertViewHas('dashboard', fn (DashboardData $data): bool => $data->shopName === '<img src=x onerror=alert(1)>' && $data->userName === '<script>alert(1)</script> Test')
        ->assertSee('<script>alert(1)</script> Test')
        ->assertSee('<img src=x onerror=alert(1)>')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('<img src=x onerror=alert(1)>', false);
});

test('an account without completed active membership receives 403 from the prepared dashboard', function (array $accessState): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    $user = User::factory()->create([
        'status' => StatusEnum::ACTIVE,
        'membership_status' => MembershipStatusEnum::ACTIVE,
        'joined_at' => now(),
        'email_verified_at' => now(),
    ]);
    $user->forceFill($accessState);

    $this->actingAs($user, 'tenant')
        ->get('/_testing/tenant-dashboard')
        ->assertForbidden();
})->with([
    'suspended account' => [['status' => StatusEnum::SUSPENDED]],
    'unaccepted invitation' => [['membership_status' => MembershipStatusEnum::INVITED]],
    'missing joining date' => [['joined_at' => null]],
    'unverified email' => [['email_verified_at' => null]],
]);

test('the prepared dashboard refuses a context whose UUID disagrees with the local shop with 403', function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);
    $user = User::factory()->create([
        'status' => StatusEnum::ACTIVE,
        'membership_status' => MembershipStatusEnum::ACTIVE,
        'joined_at' => now(),
        'email_verified_at' => now(),
    ]);
    $tenant->setAttribute('uuid', (string) Str::uuid());

    $this->actingAs($user, 'tenant')
        ->get('/_testing/tenant-dashboard')
        ->assertForbidden();
});

test('the prepared login has no submitting form until a tenant action is provided', function (): void {
    $this->withoutVite();

    $this->view('tenant.pages.auth.login')
        ->assertSee('Connexion à la boutique')
        ->assertSee('Cet espace est en préparation.')
        ->assertDontSee('<form', false);
});

test('the prepared login uses only the tenant actions explicitly provided to its view', function (): void {
    $this->withoutVite();

    $this->view('tenant.pages.auth.login', [
        'loginAction' => '/_testing/tenant-login',
        'passwordRequestUrl' => '/_testing/tenant-forgot-password',
    ])
        ->assertSee('action="/_testing/tenant-login"', false)
        ->assertSee('href="/_testing/tenant-forgot-password"', false)
        ->assertSee('name="_token"', false)
        ->assertDontSee('action="http://aydra.localhost/login"', false);
});
