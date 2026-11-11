<?php

require_once dirname(__DIR__, 4).'/Support/ManualBillingFixtures.php';

use App\Models\Central\Roles\Role;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Media\Media;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\Central\Tenants\Tenant;
use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Enums\Central\Users\StatusEnum as UserStatus;
use App\Models\User;
use App\Services\Central\Billing\SaasPaymentService;
use App\Services\Central\Billing\SaasInvoicePdfService;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
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

test('staff with either dashboard permission land directly on the administrative page after login', function (string $permission): void {
    $actor = adminDashboardActor([$permission]);

    $this->postJson(route('login.store'), ['email' => $actor->email, 'password' => 'password'])
        ->assertOk()->assertJsonPath('redirect', route('central.admin-dashboard.index'));
})->with(['saas.dashboard.view', 'saas.dashboard.financial.view']);

test('administrative login preserves an intended internal destination', function (): void {
    $actor = adminDashboardActor(['saas.dashboard.view']);
    $destination = route('appearance.edit');

    $this->withSession(['url.intended' => $destination])
        ->postJson(route('login.store'), ['email' => $actor->email, 'password' => 'password'])
        ->assertOk()->assertJsonPath('redirect', $destination);
});

test('staff without dashboard permissions keep their existing login destination', function (): void {
    $actor = adminDashboardActor(['saas.users.view']);

    $this->postJson(route('login.store'), ['email' => $actor->email, 'password' => 'password'])
        ->assertOk()->assertJsonPath('redirect', route('dashboard'));
});

test('administrative navigation points directly to the administrative dashboard', function (): void {
    $actor = adminDashboardActor(['saas.dashboard.view']);

    $this->actingAs($actor, 'central')->get(route('central.admin-dashboard.index'))
        ->assertOk()->assertSee('href="'.route('central.admin-dashboard.index').'"', false)
        ->assertDontSee('href="'.route('dashboard').'"', false);
});

test('the dashboard entry redirects authorized staff to their administrative indicators', function (): void {
    foreach ([['saas.dashboard.view'], ['saas.dashboard.financial.view']] as $permissions) {
        $actor = adminDashboardActor($permissions);

        $this->actingAs($actor, 'central')->get(route('dashboard'))
            ->assertRedirect(route('central.admin-dashboard.index'));
    }
});

test('staff without dashboard permissions keep their existing welcome page', function (): void {
    $actor = adminDashboardActor(['saas.users.view']);

    $this->actingAs($actor, 'central')->get(route('dashboard'))
        ->assertOk()->assertViewIs('central.dashboard');
});

test('the dashboard entry reaches the administrative page and preserves feedback', function (): void {
    $actor = adminDashboardActor(['saas.dashboard.view']);
    session()->flash('status', 'Opération enregistrée.');

    $this->actingAs($actor, 'central')->followingRedirects()->get(route('dashboard'))
        ->assertOk()->assertSee('Comptes commerçants')->assertSee('Opération enregistrée.');
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

test('general dashboard excludes archived merchants and shops', function (): void {
    Event::fake([TenantCreated::class]);
    $viewer = adminDashboardActor(['saas.dashboard.view']);
    $owner = User::factory()->create();
    $archivedOwner = User::factory()->create();
    $archivedOwner->delete();
    Tenant::factory()->create(['user_id' => $owner->id]);
    $archivedShop = Tenant::factory()->create(['user_id' => $owner->id]);
    $archivedShop->delete();

    $this->actingAs($viewer, 'central')->get(route('central.admin-dashboard.index'))->assertOk()
        ->assertViewHas('overview', fn (array $data): bool => array_sum($data['merchants']) === 1
            && array_sum($data['shops']) === 1);
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

test('financial dashboard aggregates verified payments without model hydration', function (): void {
    $this->partialMock(SaasInvoicePdfService::class, function ($mock): void {
        $mock->shouldReceive('generate')->once()->andReturnUsing(function (SaasInvoice $invoice): array {
            $bytes = "%PDF-1.4\n%%EOF";
            $key = (new SaasInvoicePdfService)->storageKey($invoice);
            Storage::disk('local')->put($key, $bytes);

            return ['storage_key' => $key, 'file_hash' => hash('sha256', $bytes), 'size_bytes' => strlen($bytes)];
        });
    });
    $fixture = manualPaymentFixture();
    $payment = new SaasPayment;
    $payment->forceFill(['user_id' => $fixture['owner']->id, 'document_id' => $fixture['invoice']->id,
        'record_type' => SaasPayment::RECORD_TYPE, 'transfer_method' => 4, 'transfer_status' => 1,
        'amount' => '3000.00', 'currency' => 'DZD', 'created_by_id' => $fixture['actor']->id,
        'operation_key' => 'saas:payment:test:'.Str::uuid(), 'correlation_id' => Str::uuid()])->save();
    $proof = Media::factory()->create(['model_type' => 'saas_payment', 'model_id' => $payment->id,
        'created_by_id' => $fixture['actor']->id, 'collection_name' => 'payment_proof']);
    $payment->forceFill(['transfer_status' => 3, 'proof_media_id' => $proof->id,
        'transfer_reference' => 'TEST-'.Str::uuid(), 'financial_account_key' => 'test-account',
        'transaction_fingerprint' => hash('sha256', $payment->uuid), 'occurred_at' => now(),
        'validated_by_id' => $fixture['actor']->id, 'validated_at' => now()])->save();
    DB::table('saas_transfers')->insert([
        'uuid' => Str::uuid(), 'user_id' => $fixture['owner']->id, 'document_id' => $fixture['invoice']->id,
        'original_payment_id' => $payment->id, 'created_by_id' => $fixture['actor']->id,
        'performed_by_id' => $fixture['actor']->id, 'validated_by_id' => $fixture['actor']->id,
        'operation_key' => 'saas:refund:test:'.Str::uuid(), 'record_type' => 2, 'transfer_method' => 4,
        'transfer_status' => 3, 'refund_reason' => 2, 'amount' => '1000.00', 'currency' => 'DZD',
        'reason' => 'Test', 'proof_media_id' => $proof->id, 'transfer_reference' => 'REFUND-'.Str::uuid(),
        'financial_account_key' => 'test-account', 'transaction_fingerprint' => hash('sha256', (string) Str::uuid()),
        'occurred_at' => now(), 'sending_started_at' => now(), 'validated_at' => now(), 'correlation_id' => Str::uuid(),
    ]);
    DB::table('saas_transfers')->insert([
        'uuid' => Str::uuid(), 'user_id' => $fixture['owner']->id, 'document_id' => $fixture['invoice']->id,
        'reversal_of_id' => $payment->id, 'created_by_id' => $fixture['actor']->id,
        'validated_by_id' => $fixture['actor']->id, 'operation_key' => 'saas:payment:test:'.Str::uuid(),
        'record_type' => 1, 'transfer_method' => 4, 'transfer_status' => 3, 'amount' => '-3000.00',
        'currency' => 'DZD', 'proof_media_id' => $proof->id, 'transfer_reference' => 'REVERSAL-'.Str::uuid(),
        'financial_account_key' => 'test-account', 'transaction_fingerprint' => hash('sha256', (string) Str::uuid()),
        'occurred_at' => now(), 'validated_at' => now(), 'correlation_id' => Str::uuid(),
    ]);
    $viewer = adminDashboardActor(['saas.dashboard.financial.view']);
    $retrieved = 0;
    SaasPayment::retrieved(function () use (&$retrieved): void { $retrieved++; });

    $this->actingAs($viewer, 'central')->get(route('central.admin-dashboard.index'))->assertOk()
        ->assertSee('3000.00')->assertSee('DZD');
    expect($retrieved)->toBe(0);
});
