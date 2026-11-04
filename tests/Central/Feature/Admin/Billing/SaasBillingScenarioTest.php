<?php

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\TriggerEventEnum;
use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Enums\Central\Subscriptions\PeriodEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\BillingSequence;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Billing\SaasInvoiceLine;
use App\Models\Central\Features\Feature;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\User;
use App\Services\Central\Billing\BillingRuleService;
use App\Services\Central\Billing\SaasInvoiceService;
use App\Services\Central\Billing\SaasNumberingService;
use App\Services\Central\Plans\PlanActivationService;
use App\Services\Central\Plans\PlanCompositionService;
use App\Services\Central\Plans\PlanCreationService;
use App\Services\Central\Subscriptions\SubscriptionAssignmentService;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\FreePlanSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

const INVOICE_TEST_PERMISSION_DAYS = 7;

function invoiceScenario(): array
{
    test()->seed([AuthorizationSeeder::class, FreePlanSeeder::class]);
    $actor = User::factory()->create();
    $actor->assignRole(Role::findByName('root', 'central'));
    $owner = User::factory()->create(['first_name' => 'Commerçant', 'last_name' => 'Test']);
    $plan = app(PlanCreationService::class)->create($actor, [
        'code' => 'pro', 'name' => 'Pro', 'description' => null, 'monthly_price' => '3000.00', 'annual_price' => '36000.00',
    ]);
    app(PlanCompositionService::class)->update($actor, $plan, [
        'expected_state' => $plan->compositionState($plan->features()->get()),
        'features' => [['uuid' => Feature::where('code', Feature::SHOPS_CODE)->sole()->uuid, 'is_active' => true, 'limit' => 2]],
    ]);
    $plan->refresh();
    app(PlanActivationService::class)->setActive($actor, $plan, ['is_active' => true, 'expected_state' => $plan->compositionState($plan->features()->get())]);
    $subscription = app(SubscriptionAssignmentService::class)->assign($actor, [
        'owner_uuid' => $owner->uuid, 'plan_uuid' => $plan->uuid, 'period' => PeriodEnum::MONTHLY->value,
        'operation_key' => (string) Str::uuid(), 'expected_amount' => '3000.00',
    ]);
    $rule = app(BillingRuleService::class)->create($actor, [
        'code' => 'pro.monthly', 'trigger_event' => TriggerEventEnum::INSTALLMENT_DUE->value,
        'parameters' => ['document_type' => 1, 'payment_due_days' => 0],
    ], (string) Str::uuid());
    app(BillingRuleService::class)->validateRule($actor, $rule, $rule->managementState(), 'Scénario sans taxe autorisé');
    $rule->refresh();
    app(BillingRuleService::class)->activate($actor, $rule, $rule->managementState(), now()->subDay()->toDateTimeString(), null);
    $rule->refresh();
    $data = ['billing_rule_uuid' => $rule->uuid, 'expected_state' => $subscription->transitionState(),
        'issuer_name' => 'Aydra', 'no_tax_reason' => 'Scénario de démonstration sans taxe autorisé'];

    return compact('actor', 'owner', 'plan', 'subscription', 'rule', 'data');
}

test('Pro creates one 3000 DZD installment invoice and reserved number when operations are replayed', function (): void {
    $scenario = invoiceScenario();
    $this->actingAs($scenario['actor'], 'central');
    $route = route('central.invoices.store', $scenario['subscription']);
    $this->post($route, $scenario['data'])->assertSessionHasNoErrors()->assertRedirect();
    $this->post($route, $scenario['data'])->assertSessionHasNoErrors()->assertRedirect();
    $invoice = SaasInvoice::sole();
    $expected = $invoice->managementState();
    $this->post(route('central.invoices.reserve', $invoice), ['expected_state' => $expected])->assertSessionHasNoErrors()->assertRedirect();
    $this->post(route('central.invoices.reserve', $invoice), ['expected_state' => $expected])->assertSessionHasNoErrors()->assertRedirect();

    expect($scenario['plan']->refresh()->monthly_price)->toBe('3000.00');
    expect($scenario['plan']->features()->sole()->limit)->toBe(2);
    expect($scenario['subscription']->refresh()->agreed_amount)->toBe('3000.00');
    expect(SubscriptionInstallment::sole()->installment_amount)->toBe('3000.00');
    expect(SubscriptionInstallment::sole()->installment_status)->toBe(InstallmentStatusEnum::PENDING);
    expect($invoice->refresh()->status)->toBe(DocumentStatusEnum::PREPARING);
    expect($invoice->total_amount)->toBe('3000.00');
    expect($invoice->tax_amount)->toBe('0.00');
    expect($invoice->billing_rule_snapshot['version'])->toBe(1);
    expect($invoice->number)->toBe('FAC-'.now()->format('Y').'-000001');
    expect(BillingSequence::sole()->next_number)->toBe(2);
    expect(SaasInvoice::count())->toBe(1);
    expect(SaasInvoiceLine::count())->toBe(1);
    expect($invoice->getAttribute('issued_at'))->toBeNull();
    expect($invoice->getAttribute('document_media_id'))->toBeNull();
    expect(Activity::forSubject($invoice)->where('event', 'saas_invoice_number_reserved')->count())->toBe(1);
});

test('invoice and installment pages render the persisted rule and amounts', function (): void {
    $s = invoiceScenario();
    $invoice = app(SaasInvoiceService::class)->prepareCurrentPeriodWithoutTax($s['actor'], $s['subscription'], $s['data']);
    $this->actingAs($s['actor'], 'central');
    $this->get(route('central.invoices.index'))->assertOk()->assertSee('3000.00');
    $this->get(route('central.invoices.create', $s['subscription']))->assertOk()->assertSee('pro.monthly');
    $this->get(route('central.invoices.show', $invoice))->assertOk()->assertSee('version 1')->assertSee('Réserver le numéro');
    $this->get(route('central.subscriptions.installments.index', $s['subscription']))->assertOk()->assertSee('3000.00');
    $this->get(route('central.invoices.show', $invoice->id))->assertNotFound();
});

test('all invoice and installment actions refuse staff lacking their rights', function (): void {
    $s = invoiceScenario();
    $invoice = app(SaasInvoiceService::class)->prepareCurrentPeriodWithoutTax($s['actor'], $s['subscription'], $s['data']);
    $staff = User::factory()->create();
    $staff->assignRole(Role::createWithPermissions('unrelated', ['saas.users.view' => INVOICE_TEST_PERMISSION_DAYS]));
    $this->actingAs($staff, 'central');
    $this->get(route('central.invoices.index'))->assertForbidden();
    $this->get(route('central.invoices.show', $invoice))->assertForbidden();
    $this->get(route('central.invoices.create', $s['subscription']))->assertForbidden();
    $this->get(route('central.subscriptions.installments.index', $s['subscription']))->assertForbidden();
    $this->post(route('central.invoices.store', $s['subscription']), [])->assertForbidden();
    $this->post(route('central.invoices.reserve', $invoice), [])->assertForbidden();
    expect(BillingSequence::count())->toBe(0);
});

test('preparation refuses stale subscription forms injected amounts and an inapplicable rule without creating debt', function (): void {
    $s = invoiceScenario();
    $this->actingAs($s['actor'], 'central');
    $route = route('central.invoices.store', $s['subscription']);
    $this->post($route, [...$s['data'], 'amount' => 1])->assertSessionHasErrors('amount');
    $this->post($route, [...$s['data'], 'expected_state' => str_repeat('a', 64)])->assertSessionHasErrors('conflict');
    app(BillingRuleService::class)->retire($s['actor'], $s['rule'], $s['rule']->managementState(), 'Fin de la règle de test', now()->addMinute()->toDateTimeString());
    $this->travel(2)->minutes();
    $this->post($route, $s['data'])->assertSessionHasErrors('billing_rule_uuid');
    expect(SubscriptionInstallment::count())->toBe(0);
    expect(SaasInvoice::count())->toBe(0);
});

test('combined preparation requires both installment creation and invoice creation rights', function (string $permission): void {
    $s = invoiceScenario();
    $staff = User::factory()->create();
    $staff->assignRole(Role::createWithPermissions('single-right', [$permission => INVOICE_TEST_PERMISSION_DAYS]));
    $this->actingAs($staff, 'central')->get(route('central.invoices.create', $s['subscription']))->assertForbidden();
    $this->post(route('central.invoices.store', $s['subscription']), $s['data'])->assertForbidden();
    expect(SubscriptionInstallment::count())->toBe(0);
    expect(SaasInvoice::count())->toBe(0);
})->with(['saas.subscriptions.installments.create', 'saas.invoices.create']);

test('number reservation rollback restores the draft counter and audit together', function (): void {
    $s = invoiceScenario();
    $invoice = app(SaasInvoiceService::class)->prepareCurrentPeriodWithoutTax($s['actor'], $s['subscription'], $s['data']);
    $before = Activity::count();
    expect(fn () => DB::transaction(function () use ($s, $invoice): void {
        app(SaasNumberingService::class)->reserve($s['actor'], $invoice, $invoice->managementState());
        throw new RuntimeException('Rollback reservation');
    }))->toThrow(RuntimeException::class, 'Rollback reservation');
    expect($invoice->refresh()->status)->toBe(DocumentStatusEnum::DRAFT);
    expect($invoice->number)->toBeNull();
    expect(BillingSequence::count())->toBe(0);
    expect(Activity::count())->toBe($before);
});

test('an invoice with inconsistent line totals cannot consume a number', function (): void {
    $s = invoiceScenario();
    $invoice = app(SaasInvoiceService::class)->prepareCurrentPeriodWithoutTax($s['actor'], $s['subscription'], $s['data']);
    $invoice->lines()->sole()->forceFill(['net_amount' => '9000.00', 'total_amount' => '9000.00'])->save();
    $this->actingAs($s['actor'], 'central')->post(route('central.invoices.reserve', $invoice), [
        'expected_state' => $invoice->managementState(),
    ])->assertSessionHasErrors('invoice');
    expect(BillingSequence::count())->toBe(0);
});

test('concurrent number reservations return the same number without incrementing twice', function (): void {
    $s = invoiceScenario();
    $invoice = app(SaasInvoiceService::class)->prepareCurrentPeriodWithoutTax($s['actor'], $s['subscription'], $s['data']);
    $database = DB::connection()->getDatabaseName();
    expect($database)->toStartWith('aydra_test_');
    DB::connection()->commit();
    $connection = config('database.connections.mysql');
    $env = ['APP_ENV' => 'testing', 'DB_URL' => false, 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database,
        'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_USERNAME' => $connection['username'],
        'DB_PASSWORD' => $connection['password'], 'CACHE_STORE' => 'array',
        'BILLING_TEST_ACTOR' => $s['actor']->uuid, 'BILLING_TEST_INVOICE' => $invoice->uuid];
    $script = <<<'PHP'
    require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $c=config('database.connections.mysql');
    if(!in_array($c['host'],['127.0.0.1','localhost','::1'],true)||!str_starts_with($c['database'],'aydra_test_')||!empty($c['url'])){throw new LogicException('Isolated local database required.');}
    $actor=App\Models\User::where('uuid',getenv('BILLING_TEST_ACTOR'))->firstOrFail();
    $invoice=App\Models\Central\Billing\SaasInvoice::where('uuid',getenv('BILLING_TEST_INVOICE'))->firstOrFail();
    echo app(App\Services\Central\Billing\SaasNumberingService::class)->reserve($actor,$invoice,$invoice->managementState())->number;
    PHP;
    $processes = [];
    try {
        for ($i = 0; $i < 2; $i++) {
            $p = new Process([PHP_BINARY, '-r', $script], base_path(), $env);
            $p->setTimeout(60)->start();
            $processes[] = $p;
        }
        $numbers = [];
        foreach ($processes as $p) {
            $p->wait();
            expect($p->isSuccessful())->toBeTrue($p->getErrorOutput());
            $numbers[] = trim($p->getOutput());
        }
        expect(array_unique($numbers))->toHaveCount(1);
        expect(BillingSequence::sole()->next_number)->toBe(2);
        expect(Activity::forSubject($invoice)->where('event', 'saas_invoice_number_reserved')->count())->toBe(1);
    } finally {
        foreach ($processes as $p) {
            if ($p->isRunning()) {
                $p->stop();
            }
        }
        DB::connection()->beginTransaction();
    }
});
