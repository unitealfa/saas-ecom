<?php

require_once dirname(__DIR__, 4).'/Support/ManualBillingFixtures.php';

use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\Central\Tenants\Tenant;
use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Enums\Central\Users\StatusEnum as UserStatus;
use App\Models\User;
use App\Services\Central\Billing\SaasPaymentService;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Events\TenantCreated;

function adminDashboardActor(array $permissions): User
{
    test()->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('dashboard-'.Str::random(10), array_fill_keys($permissions, 7)));

    return $actor;
}

beforeEach(function (): void {
    $this->withoutVite();
});

test('the general and financial dashboard permissions protect their own indicators', function (): void {
    $general = adminDashboardActor(['saas.dashboard.view']);
    $financial = adminDashboardActor(['saas.dashboard.financial.view']);
    $other = adminDashboardActor(['saas.users.view']);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void { $queries[] = $query->sql; });
    $this->actingAs($general, 'central')->get(route('central.admin-dashboard.index'))->assertOk()
        ->assertSee('Comptes commerçants')->assertDontSee('Paiements vérifiés');
    expect(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'saas_transfers')))->toBeFalse();
    $this->actingAs($financial, 'central')->get(route('central.admin-dashboard.index'))->assertOk()
        ->assertSee('Paiements vérifiés')->assertDontSee('Comptes commerçants');
    $this->actingAs($other, 'central')->get(route('central.admin-dashboard.index'))->assertForbidden();
    $this->actingAs(User::factory()->create(), 'central')->get(route('central.admin-dashboard.index'))->assertForbidden();
});

test('general counts exclude staff and installments while preserving shop status distinctions', function (): void {
    Event::fake([TenantCreated::class]);
    $viewer = adminDashboardActor(['saas.dashboard.view']);
    $owner = User::factory()->create(['status' => UserStatus::ACTIVE]);
    User::factory()->create(['status' => UserStatus::PENDING]);
    Tenant::factory()->create(['user_id' => $owner->id, 'status' => TenantStatus::ACTIVE]);
    Tenant::factory()->create(['user_id' => $owner->id, 'status' => TenantStatus::SUSPENDED]);
    $subscription = Subscription::factory()->create(['user_id' => $owner->id, 'period_ends_at' => now()->addMonth()]);
    $installment = new SubscriptionInstallment;
    $installment->forceFill(['user_id' => $owner->id, 'parent_subscription_id' => $subscription->id,
        'operation_key' => 'dashboard-test:'.Str::uuid(), 'installment_number' => 'TEST-'.Str::uuid(),
        'installment_amount' => '10.00', 'installment_status' => InstallmentStatusEnum::PENDING,
        'due_at' => now()->addDay(), 'period_starts_at' => $subscription->period_starts_at,
        'period_ends_at' => $subscription->period_ends_at])->save();

    $this->actingAs($viewer, 'central')->get(route('central.admin-dashboard.index'))->assertOk()
        ->assertViewHas('overview', fn (array $data): bool => array_sum($data['merchants']) === 2
            && ($data['merchants'][UserStatus::PENDING->value] ?? 0) === 1
            && ($data['shops'][TenantStatus::ACTIVE->value] ?? 0) === 1
            && ($data['shops'][TenantStatus::SUSPENDED->value] ?? 0) === 1
            && array_sum($data['subscriptions']) === 1);
});

test('financial dashboard shows verified gross payments without counting unverified anomalies', function (): void {
    $fixture = manualPaymentFixture();
    app(SaasPaymentService::class)->recordCashPayment($fixture['actor'], $fixture['invoice'], [
        'amount' => '3000.00', 'currency' => 'DZD', 'occurred_at' => now()->toDateTimeString(),
        'operation_id' => (string) Str::uuid(), 'received' => true,
    ]);
    app(SaasPaymentService::class)->recordCashPayment($fixture['actor'], $fixture['invoice'], [
        'amount' => '1500.00', 'currency' => 'DZD', 'occurred_at' => now()->toDateTimeString(),
        'operation_id' => (string) Str::uuid(), 'received' => true,
    ]);
    $viewer = adminDashboardActor(['saas.dashboard.financial.view']);
    $this->actingAs($viewer, 'central')->get(route('central.admin-dashboard.index'))->assertOk()
        ->assertSee('3000.00')->assertSee('DZD')->assertDontSee('4500.00');
});
