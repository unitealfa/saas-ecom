<?php

use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Enums\Central\Billing\PolicyStatusEnum;
use App\Enums\Central\Billing\TriggerEventEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\BillingRule;
use App\Models\Central\Billing\BillingSequence;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\User;
use App\Services\Central\Billing\BillingRuleService;
use App\Services\Central\Roles\CentralRoleCatalog;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\BillingPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

const BILLING_TEST_PERMISSION_DAYS = 7;

beforeEach(function (): void {
    $this->seed(AuthorizationSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::findByName('root', 'central'));
    $this->service = app(BillingRuleService::class);
    $this->actingAs($this->admin, 'central');
});

function billingContent(string $code = 'abonnement.standard'): array
{
    return ['code' => $code, 'trigger_event' => TriggerEventEnum::INSTALLMENT_DUE->value,
        'parameters' => ['document_type' => DocumentTypeEnum::INVOICE->value, 'payment_due_days' => 15]];
}

function billingDraft(): BillingRule
{
    return test()->service->create(test()->admin, billingContent(), (string) Str::uuid());
}

function validateBillingRuleForTest(User $actor, BillingRule $rule, string $reference = 'FACT-001'): BillingRule
{
    app(BillingRuleService::class)->validateRule($actor, $rule, $rule->managementState(), $reference);

    return $rule->refresh();
}

function billingStaff(string $permission): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::createWithPermissions('billing-'.Str::random(10), [$permission => BILLING_TEST_PERMISSION_DAYS]));

    return $user;
}

test('billing seeding is additive and does not create financial settings or change existing assignments', function (): void {
    $rootPermissions = DB::table('role_has_permissions')->count();
    $this->seed(BillingPermissionSeeder::class);
    $ids = Permission::whereIn('name', array_keys(CentralRoleCatalog::BILLING_PERMISSIONS))->pluck('id', 'name')->all();
    $this->seed(BillingPermissionSeeder::class);

    expect(Permission::whereIn('name', array_keys(CentralRoleCatalog::BILLING_PERMISSIONS))->pluck('id', 'name')->all())->toBe($ids);
    expect($ids)->toHaveCount(3);
    expect(DB::table('role_has_permissions')->count())->toBe($rootPermissions);
    expect(DB::table('saas_billing_settings')->count())->toBe(0);
});

test('all billing parameter endpoints reject staff without their permission', function (): void {
    $rule = billingDraft();
    $this->actingAs(billingStaff('saas.users.view'), 'central');
    $this->get(('/admin/billing/parameters/'.$rule->uuid))->assertNotFound();
    $this->put(('/admin/billing/parameters/'.$rule->uuid), [])->assertNotFound();
    foreach (['/admin/billing/rules', '/admin/billing/rules/create', '/admin/billing/sequences'] as $url) {
        $this->get($url)->assertNotFound();
    }
    expect(BillingRule::count())->toBe(1);
});

test('billing configuration separates reading editing and validation permissions', function (string $permission): void {
    $rule = billingDraft();
    $actor = billingStaff($permission);
    expect(Gate::forUser($actor)->allows('viewAny', BillingRule::class))->toBe($permission === 'saas.billing-rules.view')
        ->and(Gate::forUser($actor)->allows('configure', $rule))->toBeFalse();
    $this->actingAs($actor, 'central')->put(('/admin/billing/parameters/'.$rule->uuid), [])->assertNotFound();
    $response = $this->get(('/admin/billing/parameters/'.$rule->uuid));
    $response->assertNotFound();
})->with(['saas.billing-rules.view', 'saas.billing-rules.update', 'saas.billing-rules.activate']);

test('billing configuration and counter interfaces are removed', function (): void {
    $rule = billingDraft();
    $this->get('/admin/billing/parameters/'.$rule->uuid)->assertNotFound();
    $this->get(('/admin/billing/parameters/'.$rule->id))->assertNotFound();
    $this->get('/admin/billing/rules')->assertNotFound();
    $this->get('/admin/billing/sequences')->assertNotFound();
});

test('a submitted rule is a draft and repeating its operation never creates another rule', function (): void {
    $payload = [...billingContent(), 'operation_uuid' => (string) Str::uuid()];
    $this->service->create($this->admin, billingContent(), $payload['operation_uuid']);
    $this->service->create($this->admin, billingContent(), $payload['operation_uuid']);

    $rule = BillingRule::sole();
    expect($rule->policy_status)->toBe(PolicyStatusEnum::DRAFT);
    expect($rule->version)->toBe(1);
    expect($rule->parameters)->toBe(['document_type' => 1, 'payment_due_days' => 15]);
    expect($rule->validated_at)->toBeNull();
    expect(Activity::forSubject($rule)->where('event', 'saas_billing_rule_created')->count())->toBe(1);
    expect(BillingSequence::count())->toBe(0);
    expect(DB::table('saas_invoices')->count())->toBe(0);
});

test('parameter input rejects injected financial identity state and technical fields', function (): void {
    $rule = billingDraft();
    $payload = ['expected_state' => $rule->managementState(), 'payment_due_days' => 15, 'validation_reference' => 'TEST-CONFIG'];
    foreach (['code', 'policy_status', 'version', 'parameters', 'trigger_event', 'effective_at'] as $field) {
        $validation = Validator::make([...$payload, $field => 'forged'], BillingRuleService::parameterRules());
        expect($validation->fails())->toBeTrue()->and($validation->errors()->has($field))->toBeTrue();
    }
    expect(BillingRule::count())->toBe(1)->and($rule->refresh()->policy_status)->toBe(PolicyStatusEnum::DRAFT);
});

test('draft editing preserves identity and refuses stale forms and code changes internally', function (): void {
    $rule = billingDraft();
    $expected = $rule->managementState();
    $payload = billingContent();
    $payload['parameters']['payment_due_days'] = 30;
    $this->service->update($this->admin, $rule, $payload, $expected);
    expect(fn () => $this->service->update($this->admin, $rule, $payload, $expected))->toThrow(ValidationException::class);
    expect(fn () => $this->service->update($this->admin, $rule, [...$payload, 'code' => 'changed'], $rule->refresh()->managementState()))->toThrow(ValidationException::class);
    expect($rule->refresh()->parameters['payment_due_days'])->toBe(30)
        ->and($rule->policy_status)->toBe(PolicyStatusEnum::DRAFT)
        ->and(Activity::forSubject($rule)->where('event', 'saas_billing_rule_updated')->count())->toBe(1);
});

test('the payment delay accepts zero but rejects negative days and an unsupported document', function (): void {
    $payload = billingContent();
    $payload['parameters']['payment_due_days'] = -1;
    expect(fn () => $this->service->create($this->admin, $payload, (string) Str::uuid()))->toThrow(ValidationException::class);
    $payload['parameters'] = ['document_type' => DocumentTypeEnum::CREDIT_NOTE->value, 'payment_due_days' => 0];
    expect(fn () => $this->service->create($this->admin, $payload, (string) Str::uuid()))->toThrow(ValidationException::class);
    $payload['parameters']['document_type'] = DocumentTypeEnum::INVOICE->value;
    $this->service->create($this->admin, $payload, (string) Str::uuid());
    expect(BillingRule::sole()->parameters['payment_due_days'])->toBe(0);
});

test('saving parameters requires validation and freezes their contents even for root', function (): void {
    $rule = billingDraft();
    $payload = ['expected_state' => $rule->managementState(), 'payment_due_days' => 30];
    expect(fn () => $this->service->configure($this->admin, $rule, $payload))->toThrow(ValidationException::class);
    $this->service->configure($this->admin, $rule, [...$payload, 'validation_reference' => 'FACT-001']);
    expect($rule->refresh()->policy_status)->toBe(PolicyStatusEnum::ACTIVE)
        ->and($rule->getAttribute('validated_by_id'))->toBe($this->admin->id)->and($rule->validated_at)->not->toBeNull();
    expect(fn () => $rule->forceFill(['parameters' => ['document_type' => 1, 'payment_due_days' => 90]])->save())->toThrow(LogicException::class);
    expect(DB::table('saas_invoices')->count())->toBe(0);
});

test('new versions preserve historical content and activation refuses overlapping intervals', function (): void {
    $rule = billingDraft();
    $start = now()->addDay();
    $end = now()->addDays(10);
    validateBillingRuleForTest($this->admin, $rule);
    $this->service->activate($this->admin, $rule, $rule->managementState(), $start->toDateTimeString(), $end->toDateTimeString());
    $rule->refresh();
    $payload = [...billingContent(), 'expected_state' => $rule->managementState(), 'operation_uuid' => (string) Str::uuid()];
    $payload['parameters']['payment_due_days'] = 30;
    $this->service->createVersion($this->admin, $rule, array_intersect_key($payload, array_flip(['code', 'trigger_event', 'parameters'])), $payload['expected_state'], $payload['operation_uuid']);
    $this->service->createVersion($this->admin, $rule, array_intersect_key($payload, array_flip(['code', 'trigger_event', 'parameters'])), $payload['expected_state'], $payload['operation_uuid']);
    $version = BillingRule::where('version', 2)->sole();
    validateBillingRuleForTest($this->admin, $version, 'FACT-002');
    $activation = ['expected_state' => $version->managementState(), 'effective_at' => $start->toDateTimeString()];
    expect(fn () => $this->service->activate($this->admin, $version, $activation['expected_state'], $activation['effective_at'], null))->toThrow(ValidationException::class);
    $activation['effective_at'] = $end->toDateTimeString();
    $this->service->activate($this->admin, $version, $activation['expected_state'], $activation['effective_at'], null);
    expect(BillingRule::count())->toBe(2);
    expect($rule->refresh()->parameters['payment_due_days'])->toBe(15);
    expect($version->refresh()->policy_status)->toBe(PolicyStatusEnum::ACTIVE);
});

test('withdrawal requires a reason and closes the interval without allowing reuse of its history', function (): void {
    $rule = billingDraft();
    validateBillingRuleForTest($this->admin, $rule);
    $this->service->activate($this->admin, $rule, $rule->managementState(), now()->subDay()->toDateTimeString(), null);
    $rule->refresh();
    $payload = ['expected_state' => $rule->managementState(), 'ends_at' => now()->addDay()->toDateTimeString()];
    expect(fn () => $this->service->retire($this->admin, $rule, $payload['expected_state'], '', $payload['ends_at']))->toThrow(ValidationException::class);
    $this->service->retire($this->admin, $rule, $payload['expected_state'], 'Remplacement par la version suivante', $payload['ends_at']);
    $rule->refresh();
    expect($rule->policy_status)->toBe(PolicyStatusEnum::RETIRED);
    expect($rule->validation_reference)->toBe('FACT-001');
    expect(Activity::forSubject($rule)->where('event', 'saas_billing_rule_retired')->sole()->getProperty('reason'))
        ->toBe('Remplacement par la version suivante');
    $version = $this->service->createVersion($this->admin, $rule, billingContent(), $rule->managementState(), (string) Str::uuid());
    validateBillingRuleForTest($this->admin, $version, 'FACT-002');
    expect(fn () => $this->service->activate($this->admin, $version, $version->managementState(), now()->toDateTimeString(), null))
        ->toThrow(ValidationException::class);
});

test('internal configuration does not require invoice reading', function (): void {
    $rule = billingDraft();
    $actor = billingStaff('saas.billing-rules.update');
    $actor->givePermissionTo('saas.billing-rules.activate');
    $this->service->configure($actor, $rule, [
        'expected_state' => $rule->managementState(), 'payment_due_days' => 15, 'validation_reference' => 'TEST-ONLY',
    ]);
    expect(BillingRule::count())->toBe(1);
});

test('a draft already referenced by an invoice is immutable', function (): void {
    $rule = billingDraft();
    $subscription = Subscription::factory()->create(['user_id' => $this->admin->id]);
    $end = now()->addMonth();
    $installment = DB::table('subscription_installments')->insertGetId([
        'uuid' => (string) Str::uuid(), 'user_id' => $this->admin->id,
        'subscription_id' => $subscription->id, 'operation_key' => 'test:installment:'.Str::uuid(),
        'installment_number' => 'BILLING-TEST-1', 'period_starts_at' => now(), 'period_ends_at' => $end,
        'installment_amount' => 0, 'due_at' => $end, 'installment_status' => 1,
    ]);
    DB::table('saas_invoices')->insert([
        'uuid' => (string) Str::uuid(), 'user_id' => $this->admin->id, 'subscription_id' => $subscription->id,
        'installment_id' => $installment, 'billing_rule_id' => $rule->id, 'document_type' => 1,
        'operation_key' => 'saas:invoice:test-'.Str::uuid(), 'billing_rule_snapshot' => '{}',
        'net_amount' => 0, 'tax_amount' => 0, 'total_amount' => 0, 'taxes' => '[]', 'currency' => 'DZD',
        'status' => 1, 'period_starts_at' => now(), 'period_ends_at' => $end, 'due_at' => $end,
        'saas_identity_snapshot' => '{}', 'customer_identity_snapshot' => '{}',
    ]);
    $payload = [...billingContent(), 'expected_state' => $rule->managementState()];
    $payload['parameters']['payment_due_days'] = 45;
    expect(fn () => $this->service->update($this->admin, $rule, billingContent(), $rule->managementState()))->toThrow(AuthorizationException::class);
    expect($rule->refresh()->parameters['payment_due_days'])->toBe(15);
});

test('old form input does not restore the removed billing page', function (): void {
    $rule = billingDraft();
    $this->withSession(['_old_input' => ['payment_due_days' => ['invalid'], 'validation_reference' => ['invalid']]]);
    $this->get(('/admin/billing/parameters/'.$rule->uuid))->assertNotFound();
});

test('guests cannot read or write billing parameters', function (): void {
    $rule = billingDraft();
    auth('central')->logout();
    $this->get(('/admin/billing/parameters/'.$rule->uuid))->assertNotFound();
    $this->put(('/admin/billing/parameters/'.$rule->uuid), [])->assertNotFound();
    expect($rule->refresh()->policy_status)->toBe(PolicyStatusEnum::DRAFT);
});

test('billing policies reject tenant context even for a central root account', function (): void {
    tenancy()->initialized = true;
    try {
        expect(Gate::forUser($this->admin)->allows('create', BillingRule::class))->toBeFalse();
        expect(Gate::forUser($this->admin)->allows('viewAny', BillingSequence::class))->toBeFalse();
    } finally {
        tenancy()->initialized = false;
    }
});

test('rule changes and audit history are rolled back together', function (): void {
    $rule = billingDraft();
    $before = Activity::count();
    $data = billingContent();
    $data['parameters']['payment_due_days'] = 45;
    expect(fn () => DB::transaction(function () use ($rule, $data): void {
        $this->service->update($this->admin, $rule, $data, $rule->managementState());
        throw new RuntimeException('Rollback billing change');
    }))->toThrow(RuntimeException::class, 'Rollback billing change');
    expect($rule->refresh()->parameters['payment_due_days'])->toBe(15);
    expect(Activity::count())->toBe($before);
});

test('sequences are isolated read only and cannot bind as billing rules', function (): void {
    $sequence = new BillingSequence;
    $sequence->forceFill(['document_type' => DocumentTypeEnum::INVOICE, 'fiscal_year' => 2026,
        'prefix' => 'FAC', 'next_number' => 12, 'operation_key' => 'saas:sequence:invoice:2026'])->save();
    billingDraft();
    $this->get('/admin/billing/sequences')->assertNotFound();
    $this->get(('/admin/billing/parameters/'.$sequence->uuid))->assertNotFound();
    expect(BillingRule::count())->toBe(1);
    expect(BillingSequence::count())->toBe(1);
    expect($sequence->refresh()->next_number)->toBe(12);
});

test('sensitive rule fields cannot be mass assigned and SQL rejects duplicate versions', function (): void {
    $rule = billingDraft();
    expect(fn () => (new BillingRule)->fill(['policy_status' => 3]))->toThrow(MassAssignmentException::class);
    $duplicate = $rule->getAttributes();
    unset($duplicate['id'], $duplicate['sequence_slot']);
    $duplicate['uuid'] = (string) Str::uuid();
    $duplicate['operation_key'] = 'saas:rule:duplicate';
    expect(fn () => DB::table('saas_billing_settings')->insert($duplicate))->toThrow(UniqueConstraintViolationException::class);
});

test('concurrent activations of overlapping versions allow only one success', function (): void {
    $first = billingDraft();
    $second = $this->service->createVersion($this->admin, $first, billingContent(), $first->managementState(), (string) Str::uuid());
    validateBillingRuleForTest($this->admin, $first);
    validateBillingRuleForTest($this->admin, $second);
    $database = DB::connection()->getDatabaseName();
    expect($database)->toStartWith('aydra_test_');
    DB::connection()->commit();
    $connection = config('database.connections.mysql');
    $environment = [
        'APP_ENV' => 'testing', 'DB_URL' => false, 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database,
        'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'],
        'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
        'CACHE_STORE' => 'array', 'AYDRA_TEST_ACTOR' => $this->admin->uuid,
        'AYDRA_TEST_START' => now()->addDay()->toDateTimeString(),
    ];
    $script = <<<'PHP'
    require 'vendor/autoload.php';
    $app = require 'bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $connection = config('database.connections.mysql');
    if (! in_array($connection['host'], ['127.0.0.1', 'localhost', '::1'], true)
        || ! str_starts_with($connection['database'], 'aydra_test_') || ! empty($connection['url'])) {
        throw new LogicException('Only isolated local databases may be used.');
    }
    $actor = App\Models\User::where('uuid', getenv('AYDRA_TEST_ACTOR'))->firstOrFail();
    $rule = App\Models\Central\Billing\BillingRule::where('uuid', getenv('AYDRA_TEST_RULE'))->firstOrFail();
    try {
        app(App\Services\Central\Billing\BillingRuleService::class)->activate(
            $actor, $rule, $rule->managementState(), getenv('AYDRA_TEST_START'), null,
        );
        echo 'activated';
    } catch (Illuminate\Validation\ValidationException $exception) {
        if (! isset($exception->errors()['effective_at'])) {
            throw $exception;
        }
        echo 'overlap';
    }
    PHP;
    $processes = [];
    try {
        foreach ([$first, $second] as $rule) {
            $process = new Process([PHP_BINARY, '-r', $script], base_path(), [...$environment, 'AYDRA_TEST_RULE' => $rule->uuid]);
            $process->setTimeout(60);
            $process->start();
            $processes[] = $process;
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
            $results[] = trim($process->getOutput());
        }
        sort($results);
        expect($results)->toBe(['activated', 'overlap']);
        expect(BillingRule::where('policy_status', PolicyStatusEnum::ACTIVE)->count())->toBe(1);
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        DB::connection()->beginTransaction();
    }
});

test('T18-02 malformed technical inputs never alter saved parameters', function (array $change, string $field): void {
    $rule = billingDraft();
    $validation = Validator::make([
        'expected_state' => $rule->managementState(), 'payment_due_days' => 15, 'validation_reference' => 'TEST', ...$change,
    ], BillingRuleService::parameterRules());
    expect($validation->fails())->toBeTrue()->and($validation->errors()->has($field))->toBeTrue();
    expect(BillingRule::count())->toBe(1)->and($rule->refresh()->policy_status)->toBe(PolicyStatusEnum::DRAFT);
})->with([
    [['code' => null], 'code'], [['version' => null], 'version'], [['version' => 0], 'version'],
    [['parameters' => '{invalid json'], 'parameters'], [['trigger_event' => 'arbitrary.script'], 'trigger_event'],
    [['operation_uuid' => 'invalid-uuid'], 'operation_uuid'],
]);

test('version period and state filters return the matching history without mutating settings', function (): void {
    $first = billingDraft();
    validateBillingRuleForTest($this->admin, $first);
    $this->service->activate($this->admin, $first, $first->managementState(), '2030-01-01', '2030-02-01');
    $first->refresh();
    $second = $this->service->createVersion($this->admin, $first, billingContent(), $first->managementState(), (string) Str::uuid());
    validateBillingRuleForTest($this->admin, $second);
    $this->service->activate($this->admin, $second, $second->managementState(), '2030-02-01', null);
    $rules = $this->service->search($this->admin, [
        'search' => 'abonnement', 'policy_status' => PolicyStatusEnum::ACTIVE->value, 'version' => 2,
        'period_start' => '2030-02-01T00:00', 'period_end' => '2030-03-01T00:00',
    ]);
    expect($rules->total())->toBe(1)->and($rules->first()->id)->toBe($second->id)
        ->and($this->service->versions($this->admin, $first)->total())->toBe(2);
    expect(BillingRule::count())->toBe(2);
    expect(BillingSequence::count())->toBe(0);
});

test('validation audit preserves the actor and native correlation while escaping the reference in the interface', function (): void {
    $rule = billingDraft();
    $reference = '<script>window.module18Xss = true</script>';
    $this->service->validateRule($this->admin, $rule, $rule->managementState(), $reference);
    $rule->refresh();
    $audit = Activity::forSubject($rule)->where('event', 'saas_billing_rule_validated')->sole();
    expect($audit->getAttribute('correlation_id'))->toBe($rule->correlation_id);
    expect($audit->getAttribute('causer_id'))->toBe($this->admin->id);
    $this->get('/admin/billing/parameters/'.$rule->uuid)->assertNotFound();
    expect($rule->policy_status)->toBe(PolicyStatusEnum::VALIDATED);
});

test('a failed validation transaction restores the draft and its audit trail', function (): void {
    $rule = billingDraft();
    $before = Activity::count();
    expect(fn () => DB::transaction(function () use ($rule): void {
        $this->service->validateRule($this->admin, $rule, $rule->managementState(), 'Validation à annuler');
        throw new RuntimeException('Rollback validation');
    }))->toThrow(RuntimeException::class, 'Rollback validation');
    expect($rule->refresh()->policy_status)->toBe(PolicyStatusEnum::DRAFT);
    expect($rule->validated_at)->toBeNull();
    expect(Activity::count())->toBe($before);
});

test('forged validation and retirement transitions never bypass the current rule state', function (): void {
    $rule = billingDraft();
    expect(fn () => $this->service->retire($this->admin, $rule, $rule->managementState(), 'Invalid', '2030-01-01'))->toThrow(AuthorizationException::class);
    validateBillingRuleForTest($this->admin, $rule);
    expect(fn () => $this->service->validateRule($this->admin, $rule, $rule->managementState(), 'Autre référence'))->toThrow(AuthorizationException::class);
    expect($rule->refresh()->validation_reference)->toBe('FACT-001')->and($rule->policy_status)->toBe(PolicyStatusEnum::VALIDATED);
});

test('the first parameter configuration is explicit validated and idempotent without manual counters', function (): void {
    $this->get('/admin/billing/parameters')->assertNotFound();
    $payload = ['document_type' => DocumentTypeEnum::INVOICE->value, 'payment_due_days' => 0,
        'operation_uuid' => (string) Str::uuid(), 'validation_reference' => 'TEST-INITIAL'];
    $this->service->configureInitial($this->admin, $payload);
    $this->service->configureInitial($this->admin, $payload);
    $rule = BillingRule::sole();
    expect($rule->policy_status)->toBe(PolicyStatusEnum::ACTIVE)->and($rule->validation_reference)->toBe('TEST-INITIAL')
        ->and($rule->parameters['payment_due_days'])->toBe(0)->and(BillingSequence::count())->toBe(0)
        ->and(DB::table('saas_invoices')->count())->toBe(0);
});

test('saving active parameters preserves historical contents and creates the next internal version', function (): void {
    $rule = billingDraft();
    validateBillingRuleForTest($this->admin, $rule);
    $this->service->activate($this->admin, $rule, $rule->managementState(), now()->subDay()->toDateTimeString(), null);
    $rule->refresh();
    $this->service->configure($this->admin, $rule, ['expected_state' => $rule->managementState(),
        'payment_due_days' => 30, 'validation_reference' => 'TEST-NEXT']);
    $next = BillingRule::where('version', 2)->sole();
    expect($rule->refresh()->parameters['payment_due_days'])->toBe(15)
        ->and($rule->policy_status)->toBe(PolicyStatusEnum::RETIRED)
        ->and($next->parameters['payment_due_days'])->toBe(30)->and($next->policy_status)->toBe(PolicyStatusEnum::ACTIVE)
        ->and($rule->ends_at->equalTo($next->effective_at))->toBeTrue();
});
