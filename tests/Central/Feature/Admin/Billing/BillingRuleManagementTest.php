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
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
    expect($ids)->toHaveCount(6);
    expect(DB::table('role_has_permissions')->count())->toBe($rootPermissions);
    expect(DB::table('saas_billing_settings')->count())->toBe(0);
});

test('all billing endpoints reject staff without their permission', function (): void {
    $rule = billingDraft();
    $this->actingAs(billingStaff('saas.users.view'), 'central');
    foreach (['index', 'create'] as $action) {
        $this->get(route('central.billing-rules.'.$action))->assertForbidden();
    }
    foreach (['show', 'edit', 'versions.create'] as $action) {
        $this->get(route('central.billing-rules.'.$action, $rule))->assertForbidden();
    }
    $this->get(route('central.billing-sequences.index'))->assertForbidden();
    $this->post(route('central.billing-rules.store'), [])->assertForbidden();
    $this->post(route('central.billing-rules.versions.store', $rule), [])->assertForbidden();
    $this->put(route('central.billing-rules.update', $rule), [])->assertForbidden();
    $this->patch(route('central.billing-rules.activate', $rule), [])->assertForbidden();
    $this->patch(route('central.billing-rules.validate', $rule), [])->assertForbidden();
    $this->post(route('central.billing-sequences.initialize'), ['document_type' => 1, 'fiscal_year' => 2030])->assertForbidden();
    $this->patch(route('central.billing-rules.retire', $rule), [])->assertForbidden();
    expect(BillingRule::count())->toBe(1);
});

test('billing permissions remain independent including sequences', function (string $permission, string $ability): void {
    $rule = billingDraft();
    $validated = test()->service->create(test()->admin, billingContent('validated'), (string) Str::uuid());
    validateBillingRuleForTest(test()->admin, $validated);
    $actor = billingStaff($permission);
    foreach (['viewAny' => BillingRule::class, 'create' => BillingRule::class,
        'update' => $rule, 'activate' => $validated, 'validateRule' => $rule, 'createVersion' => $rule] as $candidate => $subject) {
        expect(Gate::forUser($actor)->allows($candidate, $subject))->toBe($candidate === $ability || ($candidate === 'validateRule' && $ability === 'activate'));
    }
    expect(Gate::forUser($actor)->allows('viewAny', BillingSequence::class))->toBe($ability === 'sequences');
})->with([
    ['saas.billing-rules.view', 'viewAny'], ['saas.billing-rules.create', 'create'],
    ['saas.billing-rules.update', 'update'], ['saas.billing-rules.activate', 'activate'],
    ['saas.billing-rules.versions.create', 'createVersion'], ['saas.billing-sequences.view', 'sequences'],
]);

test('the administrative pages render forms and navigation without requiring existing billing data', function (): void {
    $this->get(route('central.billing-rules.index'))->assertOk()->assertSee('Facturation')->assertSee('Créer une règle');
    $this->get(route('central.billing-rules.create'))->assertOk()->assertSee('Délai de paiement en jours');
    $this->get(route('central.billing-sequences.index'))->assertOk()->assertSee('Aucun compteur créé');
    $rule = billingDraft();
    $this->get(route('central.billing-rules.show', $rule))->assertOk()->assertSee('Valider la version')->assertDontSee('Activer la version');
    $this->get(route('central.billing-rules.edit', $rule))->assertOk();
    $this->get(route('central.billing-rules.versions.create', $rule))->assertOk();
    $this->get(route('central.billing-rules.show', $rule->id))->assertNotFound();
});

test('a submitted rule is a draft and repeating its operation never creates another rule', function (): void {
    $payload = [...billingContent(), 'operation_uuid' => (string) Str::uuid()];
    $this->post(route('central.billing-rules.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->post(route('central.billing-rules.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();

    $rule = BillingRule::sole();
    expect($rule->policy_status)->toBe(PolicyStatusEnum::DRAFT);
    expect($rule->version)->toBe(1);
    expect($rule->parameters)->toBe(['document_type' => 1, 'payment_due_days' => 15]);
    expect($rule->validated_at)->toBeNull();
    expect(Activity::forSubject($rule)->where('event', 'saas_billing_rule_created')->count())->toBe(1);
    expect(BillingSequence::count())->toBe(0);
    expect(DB::table('saas_invoices')->count())->toBe(0);
});

test('rule input rejects unknown parameters injected state unknown events and duplicate codes', function (): void {
    billingDraft();
    $payload = [...billingContent(), 'operation_uuid' => (string) Str::uuid()];
    $this->post(route('central.billing-rules.store'), $payload)->assertSessionHasErrors('code');
    $this->post(route('central.billing-rules.store'), [...$payload, 'code' => 'other', 'policy_status' => 3])
        ->assertSessionHasErrors('policy_status');
    $this->post(route('central.billing-rules.store'), [...$payload, 'trigger_event' => 'exec.script'])
        ->assertSessionHasErrors('trigger_event');
    $payload['parameters']['script'] = 'invalid';
    $this->post(route('central.billing-rules.store'), $payload)->assertSessionHasErrors('parameters');
    expect(BillingRule::count())->toBe(1);
});

test('draft editing preserves identity and refuses stale forms and code changes', function (): void {
    $rule = billingDraft();
    $expected = $rule->managementState();
    $payload = [...billingContent(), 'expected_state' => $expected];
    $payload['parameters']['payment_due_days'] = 30;
    $this->put(route('central.billing-rules.update', $rule), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->put(route('central.billing-rules.update', $rule), $payload)->assertSessionHasErrors('conflict');
    $this->put(route('central.billing-rules.update', $rule), [...$payload, 'code' => 'changed'])->assertSessionHasErrors('code');
    expect($rule->refresh()->parameters['payment_due_days'])->toBe(30);
    expect($rule->policy_status)->toBe(PolicyStatusEnum::DRAFT);
    expect(Activity::forSubject($rule)->where('event', 'saas_billing_rule_updated')->count())->toBe(1);
});

test('the payment delay accepts zero but rejects negative days and an unsupported document', function (): void {
    $payload = [...billingContent(), 'operation_uuid' => (string) Str::uuid()];
    $payload['parameters']['payment_due_days'] = -1;
    $this->post(route('central.billing-rules.store'), $payload)->assertSessionHasErrors('parameters.payment_due_days');
    $payload['parameters'] = ['document_type' => DocumentTypeEnum::CREDIT_NOTE->value, 'payment_due_days' => 0];
    $this->post(route('central.billing-rules.store'), $payload)->assertSessionHasErrors('parameters.document_type');
    $payload['parameters']['document_type'] = DocumentTypeEnum::INVOICE->value;
    $this->post(route('central.billing-rules.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
    expect(BillingRule::sole()->parameters['payment_due_days'])->toBe(0);
});

test('activation requires proof and dates then freezes the rule content even for root', function (): void {
    $rule = billingDraft();
    $payload = ['expected_state' => $rule->managementState(), 'effective_at' => now()->addDay()->toDateTimeString()];
    $this->patch(route('central.billing-rules.activate', $rule), $payload)->assertForbidden();
    $this->patch(route('central.billing-rules.validate', $rule), ['expected_state' => $rule->managementState()])->assertSessionHasErrors('validation_reference');
    $this->patch(route('central.billing-rules.validate', $rule), [
        'expected_state' => $rule->managementState(), 'validation_reference' => 'Décision interne FACT-001',
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($rule->refresh()->policy_status)->toBe(PolicyStatusEnum::VALIDATED);
    $payload['expected_state'] = $rule->managementState();
    $this->patch(route('central.billing-rules.activate', $rule), $payload)->assertSessionHasNoErrors()->assertRedirect();

    $rule->refresh();
    expect($rule->policy_status)->toBe(PolicyStatusEnum::ACTIVE);
    expect($rule->getAttribute('validated_by_id'))->toBe($this->admin->id);
    expect($rule->validated_at)->not->toBeNull();
    $this->put(route('central.billing-rules.update', $rule), [...billingContent(), 'expected_state' => $rule->managementState()])->assertForbidden();
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
    $this->post(route('central.billing-rules.versions.store', $rule), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->post(route('central.billing-rules.versions.store', $rule), $payload)->assertSessionHasNoErrors()->assertRedirect();
    $version = BillingRule::where('version', 2)->sole();
    validateBillingRuleForTest($this->admin, $version, 'FACT-002');
    $activation = ['expected_state' => $version->managementState(), 'effective_at' => $start->toDateTimeString()];
    $this->patch(route('central.billing-rules.activate', $version), $activation)->assertSessionHasErrors('effective_at');
    $activation['effective_at'] = $end->toDateTimeString();
    $this->patch(route('central.billing-rules.activate', $version), $activation)->assertSessionHasNoErrors()->assertRedirect();
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
    $this->patch(route('central.billing-rules.retire', $rule), $payload)->assertSessionHasErrors('reason');
    $this->patch(route('central.billing-rules.retire', $rule), [...$payload, 'reason' => 'Remplacement par la version suivante'])
        ->assertSessionHasNoErrors()->assertRedirect();
    $rule->refresh();
    expect($rule->policy_status)->toBe(PolicyStatusEnum::RETIRED);
    expect($rule->validation_reference)->toBe('FACT-001');
    expect(Activity::forSubject($rule)->where('event', 'saas_billing_rule_retired')->sole()->getProperty('reason'))
        ->toBe('Remplacement par la version suivante');
    $this->get(route('central.billing-rules.show', $rule))->assertOk()->assertSee('Retrait programmé');
    $this->patch(route('central.billing-rules.activate', $rule), [])->assertForbidden();
    $version = $this->service->createVersion($this->admin, $rule, billingContent(), $rule->managementState(), (string) Str::uuid());
    validateBillingRuleForTest($this->admin, $version, 'FACT-002');
    expect(fn () => $this->service->activate($this->admin, $version, $version->managementState(), now()->toDateTimeString(), null))
        ->toThrow(ValidationException::class);
});

test('a writer without read permission is redirected to the dashboard after saving', function (): void {
    $this->actingAs(billingStaff('saas.billing-rules.create'), 'central')
        ->post(route('central.billing-rules.store'), [...billingContent(), 'operation_uuid' => (string) Str::uuid()])
        ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
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
    $this->put(route('central.billing-rules.update', $rule), $payload)->assertForbidden();
    $this->get(route('central.billing-rules.edit', $rule))->assertForbidden();
    $this->get(route('central.billing-rules.index'))->assertOk()->assertDontSee(route('central.billing-rules.edit', $rule), false);
    expect($rule->refresh()->parameters['payment_due_days'])->toBe(15);
});

test('malformed previous input never breaks the billing forms', function (): void {
    $rule = billingDraft();
    $this->withSession(['_old_input' => [
        'code' => ['invalid'], 'operation_uuid' => ['invalid'], 'parameters' => ['payment_due_days' => ['invalid']],
        'validation_reference' => ['invalid'], 'effective_at' => ['invalid'], 'ends_at' => ['invalid'],
    ]]);
    $this->get(route('central.billing-rules.create'))->assertOk();
    $this->get(route('central.billing-rules.show', $rule))->assertOk();
});

test('guests cannot read or write billing settings', function (): void {
    auth('central')->logout();
    $this->get(route('central.billing-rules.index'))->assertRedirect(route('login'));
    $this->get(route('central.billing-sequences.index'))->assertRedirect(route('login'));
    $this->post(route('central.billing-rules.store'), billingContent())->assertRedirect(route('login'));
    expect(BillingRule::count())->toBe(0);
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
    $this->actingAs(billingStaff('saas.billing-sequences.view'), 'central');
    $this->get(route('central.billing-sequences.index'))->assertOk()->assertSee('FAC')->assertSee('12');
    $this->get(route('central.billing-rules.show', $sequence->uuid))->assertNotFound();
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

test('T18-02 malformed inputs including a null server version never persist a rule', function (array $change, string $field): void {
    $data = [...billingContent(), 'operation_uuid' => (string) Str::uuid(), ...$change];
    $this->post(route('central.billing-rules.store'), $data)->assertSessionHasErrors($field);
    expect(BillingRule::count())->toBe(0);
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
    $this->get(route('central.billing-rules.index', [
        'search' => 'abonnement', 'policy_status' => PolicyStatusEnum::ACTIVE->value, 'version' => 2,
        'period_start' => '2030-02-01T00:00', 'period_end' => '2030-03-01T00:00',
    ]))->assertOk()->assertViewHas('rules', fn ($rules): bool => $rules->total() === 1 && $rules->first()->id === $second->id);
    $this->get(route('central.billing-rules.show', $first))->assertOk()->assertSee('Historique des versions')
        ->assertViewHas('versions', fn ($versions): bool => $versions->total() === 2);
    $this->get(route('central.billing-rules.index', ['period_start' => '2030-03-01', 'period_end' => '2030-02-01']))
        ->assertSessionHasErrors('period_end');
    expect(BillingRule::count())->toBe(2);
    expect(BillingSequence::count())->toBe(0);
});

test('validation audit preserves the actor and native correlation while escaping the reference in the interface', function (): void {
    $rule = billingDraft();
    $reference = '<script>window.module18Xss = true</script>';
    $this->patch(route('central.billing-rules.validate', $rule), [
        'expected_state' => $rule->managementState(), 'validation_reference' => $reference,
    ])->assertSessionHasNoErrors()->assertRedirect();
    $rule->refresh();
    $audit = Activity::forSubject($rule)->where('event', 'saas_billing_rule_validated')->sole();
    expect($audit->getAttribute('correlation_id'))->toBe($rule->correlation_id);
    expect($audit->getAttribute('causer_id'))->toBe($this->admin->id);
    $this->get(route('central.billing-rules.show', $rule))->assertOk()->assertSee($reference)->assertDontSee($reference, false);
    $this->get(route('central.billing-rules.edit', $rule))->assertForbidden();
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
    $this->patch(route('central.billing-rules.retire', $rule), [
        'expected_state' => $rule->managementState(), 'reason' => 'Invalid', 'ends_at' => '2030-01-01',
    ])->assertForbidden();
    validateBillingRuleForTest($this->admin, $rule);
    $this->patch(route('central.billing-rules.validate', $rule), [
        'expected_state' => $rule->managementState(), 'validation_reference' => 'Remplacer une preuve',
    ])->assertForbidden();
    expect($rule->refresh()->validation_reference)->toBe('FACT-001');
    expect($rule->policy_status)->toBe(PolicyStatusEnum::VALIDATED);
});
