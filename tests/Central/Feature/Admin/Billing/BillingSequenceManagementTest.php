<?php

use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Enums\Central\Billing\TriggerEventEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\BillingSequence;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Tenants\Tenant;
use App\Models\User;
use App\Services\Central\Billing\BillingRuleService;
use App\Services\Central\Billing\SaasInvoiceService;
use App\Services\Central\Billing\SaasNumberingService;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

const SEQUENCE_TEST_PERMISSION_DAYS = 7;

function sequenceAdmin(): User
{
    test()->seed(AuthorizationSeeder::class);
    Permission::firstOrCreate(
        ['name' => DocumentTypeEnum::CREDIT_NOTE->issuePermission(), 'guard_name' => 'central'],
        ['label' => 'Émettre un avoir (scénario isolé)'],
    );
    $actor = User::factory()->create();
    $actor->assignRole(Role::findByName('root', 'central'));

    return $actor;
}

function sequenceActor(string $permission): User
{
    Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'central'], ['label' => 'Droit du test']);
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('sequence-'.Str::random(8), [$permission => SEQUENCE_TEST_PERMISSION_DAYS]));

    return $actor;
}

/** @return list<SaasInvoice> */
function sequenceDrafts(User $actor, int $count): array
{
    $service = app(BillingRuleService::class);
    $rule = $service->create($actor, ['code' => 'sequence-test', 'trigger_event' => TriggerEventEnum::INSTALLMENT_DUE->value,
        'parameters' => ['document_type' => 1, 'payment_due_days' => 10]], (string) Str::uuid());
    $service->validateRule($actor, $rule, $rule->managementState(), 'Validation de test');
    $rule->refresh();
    $service->activate($actor, $rule, $rule->managementState(), now()->subDay()->toDateTimeString(), null);
    $invoices = [];
    for ($i = 0; $i < $count; $i++) {
        $owner = User::factory()->create(['first_name' => 'Test']);
        $subscription = Subscription::factory()->create(['user_id' => $owner->id, 'agreed_amount' => '3000.00',
            'period_starts_at' => now(), 'period_ends_at' => now()->addMonth()]);
        $invoices[] = app(SaasInvoiceService::class)->prepareCurrentPeriodWithoutTax($actor, $subscription, [
            'billing_rule_uuid' => $rule->uuid, 'expected_state' => $subscription->transitionState(),
            'issuer_name' => 'Aydra Test', 'no_tax_reason' => 'Démonstration isolée autorisée',
        ]);
    }

    return $invoices;
}

/** @param list<string> $subjects
 * @return list<string>
 */
function runSequenceProcesses(User $actor, string $script, array $subjects): array
{
    $database = DB::connection()->getDatabaseName();
    expect($database)->toStartWith('aydra_test_');
    DB::connection()->commit();
    $c = config('database.connections.mysql');
    $env = ['APP_ENV' => 'testing', 'DB_URL' => false, 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database,
        'DB_HOST' => $c['host'], 'DB_PORT' => (string) $c['port'], 'DB_USERNAME' => $c['username'],
        'DB_PASSWORD' => $c['password'], 'CACHE_STORE' => 'array', 'SEQUENCE_ACTOR' => $actor->uuid];
    $bootstrap = <<<'PHP'
    require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $c=config('database.connections.mysql');
    if(!in_array($c['host'],['127.0.0.1','localhost','::1'],true)||!str_starts_with($c['database'],'aydra_test_')||!empty($c['url'])){throw new LogicException('Isolated local database required.');}
    $actor=App\Models\User::where('uuid',getenv('SEQUENCE_ACTOR'))->firstOrFail();
    PHP;
    $processes = [];
    try {
        foreach ($subjects as $subject) {
            $process = new Process([PHP_BINARY, '-r', $bootstrap.$script], base_path(), [...$env, 'SEQUENCE_SUBJECT' => $subject]);
            $process->setTimeout(60)->start();
            $processes[] = $process;
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
            $results[] = trim($process->getOutput());
        }

        return $results;
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        DB::connection()->beginTransaction();
    }
}

test('T18-11 and T18-12 initialize independent invoice and credit series exactly once', function (): void {
    $actor = sequenceAdmin();
    $this->actingAs($actor, 'central');
    foreach (DocumentTypeEnum::cases() as $type) {
        $payload = ['document_type' => $type->value, 'fiscal_year' => 2030];
        $this->post(route('central.billing-sequences.initialize'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('central.billing-sequences.initialize'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $sequence = BillingSequence::where('document_type', $type)->sole();
        expect($sequence->next_number)->toBe(1);
        expect($sequence->getAttribute('sequence_slot'))->toBe(1);
        foreach (['code', 'version', 'parameters', 'policy_status', 'created_by_id', 'validated_by_id', 'correlation_id'] as $column) {
            expect($sequence->getAttribute($column))->toBeNull();
        }
        expect(Activity::forSubject($sequence)->where('event', 'saas_sequence_initialized')->count())->toBe(1);
    }
    $this->get(route('central.billing-sequences.index'))->assertOk()->assertSee('FAC')->assertSee('AVOIR');
    $this->get(route('central.billing-sequences.index'))->assertOk();
    expect(BillingSequence::count())->toBe(2);
    expect(BillingSequence::pluck('next_number')->all())->toBe([1, 1]);
});

test('counter initialization requires the matching issue right and never a read right alone', function (string $permission, int $type, bool $allowed): void {
    sequenceAdmin();
    $this->actingAs(sequenceActor($permission), 'central');
    $response = $this->post(route('central.billing-sequences.initialize'), ['document_type' => $type, 'fiscal_year' => 2031]);
    if ($allowed) {
        $response->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    } else {
        $response->assertForbidden();
    }
    expect(BillingSequence::count())->toBe($allowed ? 1 : 0);
})->with([
    ['saas.billing-sequences.view', 1, false], ['saas.billing-rules.activate', 1, false],
    ['saas.invoices.issue', 1, true], ['saas.invoices.issue', 2, false],
    ['saas.credit-notes.issue', 2, true], ['saas.credit-notes.issue', 1, false],
]);

test('a counter reader sees no initialization action and forged values are rejected', function (): void {
    $actor = sequenceAdmin();
    $this->actingAs(sequenceActor('saas.billing-sequences.view'), 'central')
        ->get(route('central.billing-sequences.index'))->assertOk()->assertDontSee('Initialiser le compteur');
    $this->actingAs($actor, 'central')->post(route('central.billing-sequences.initialize'), [
        'document_type' => 1, 'fiscal_year' => 2031, 'next_number' => 800, 'prefix' => 'BAD',
    ])->assertSessionHasErrors(['next_number', 'prefix']);
    expect(BillingSequence::count())->toBe(0);
});

test('real SQL uniqueness and generated counter shape prevent duplicate or contradictory settings', function (): void {
    $actor = sequenceAdmin();
    $sequence = app(SaasNumberingService::class)->initialize($actor, DocumentTypeEnum::INVOICE, 2031);
    $duplicate = new BillingSequence;
    $duplicate->forceFill(['document_type' => DocumentTypeEnum::INVOICE, 'fiscal_year' => 2031,
        'prefix' => 'OTHER', 'next_number' => 1, 'operation_key' => 'saas:sequence:duplicate']);
    expect(fn () => $duplicate->save())->toThrow(UniqueConstraintViolationException::class);
    $sequence->forceFill(['parameters' => ['forbidden' => true]]);
    expect(fn () => $sequence->save())->toThrow(QueryException::class);
    expect(BillingSequence::sole()->prefix)->toBe('FAC');
});

test('counter initialization rollback removes both the counter and its audit', function (): void {
    $actor = sequenceAdmin();
    $before = Activity::count();
    expect(fn () => DB::transaction(function () use ($actor): void {
        app(SaasNumberingService::class)->initialize($actor, DocumentTypeEnum::CREDIT_NOTE, 2032);
        throw new RuntimeException('Rollback counter');
    }))->toThrow(RuntimeException::class, 'Rollback counter');
    expect(BillingSequence::count())->toBe(0);
    expect(Activity::count())->toBe($before);
});

test('two concurrent initializations find a single counter and do not consume a number', function (): void {
    $actor = sequenceAdmin();
    $script = <<<'PHP'
    echo app(App\Services\Central\Billing\SaasNumberingService::class)->initialize($actor,App\Enums\Central\Billing\DocumentTypeEnum::INVOICE,2033)->uuid;
    PHP;
    $results = runSequenceProcesses($actor, $script, ['same', 'same']);
    expect(array_unique($results))->toHaveCount(1);
    expect(BillingSequence::sole()->next_number)->toBe(1);
    expect(Activity::where('event', 'saas_sequence_initialized')->count())->toBe(1);
});

test('T18-15 two different concurrent reservations receive distinct consecutive numbers', function (): void {
    $actor = sequenceAdmin();
    $invoices = sequenceDrafts($actor, 2);
    $script = <<<'PHP'
    $invoice=App\Models\Central\Billing\SaasInvoice::where('uuid',getenv('SEQUENCE_SUBJECT'))->firstOrFail();
    echo app(App\Services\Central\Billing\SaasNumberingService::class)->reserve($actor,$invoice,$invoice->managementState())->number;
    PHP;
    $numbers = runSequenceProcesses($actor, $script, array_map(fn (SaasInvoice $invoice): string => $invoice->uuid, $invoices));
    sort($numbers);
    expect($numbers)->toBe(['FAC-'.now()->year.'-000001', 'FAC-'.now()->year.'-000002']);
    expect(BillingSequence::sole()->next_number)->toBe(3);
    expect(SaasInvoice::whereNotNull('number')->count())->toBe(2);
});

test('a reserved counter cannot move backwards or change its document type year or prefix', function (): void {
    $actor = sequenceAdmin();
    $invoice = sequenceDrafts($actor, 1)[0];
    app(SaasNumberingService::class)->reserve($actor, $invoice, $invoice->managementState());
    foreach (['next_number' => 1, 'prefix' => 'NEW', 'fiscal_year' => 2040, 'document_type' => DocumentTypeEnum::CREDIT_NOTE] as $field => $value) {
        $sequence = BillingSequence::sole();
        expect(fn () => $sequence->forceFill([$field => $value])->save())->toThrow(QueryException::class);
    }
    expect(BillingSequence::sole()->next_number)->toBe(2);
});

test('T18-17 central settings and audits stay central and tenant context cannot initialize a counter', function (): void {
    $actor = sequenceAdmin();
    $subscription = Subscription::factory()->create(['user_id' => $actor->id]);
    Event::fake([TenantCreated::class]);
    $tenant = Tenant::factory()->create(['slug' => 'billing-'.Str::lower(Str::random(8))]);
    $database = $tenant->database()->getName();
    expect($database)->toStartWith(config('tenancy.database.prefix'));
    DB::connection()->commit();
    try {
        CreateDatabase::dispatchSync($tenant);
        $tenant->run(function (): void {
            DB::statement('CREATE TABLE billing_canary (id INT PRIMARY KEY, value INT NOT NULL)');
            DB::table('billing_canary')->insert(['id' => 1, 'value' => 42]);
        });
        $sequence = app(SaasNumberingService::class)->initialize($actor, DocumentTypeEnum::INVOICE, 2034);
        $tenant->run(function () use ($actor, $sequence, $subscription): void {
            expect(fn () => app(SaasNumberingService::class)->initialize($actor, DocumentTypeEnum::INVOICE, 2035))
                ->toThrow(AuthorizationException::class);
            expect(fn () => app(SaasInvoiceService::class)->prepareCurrentPeriodWithoutTax($actor, $subscription, []))
                ->toThrow(HttpException::class);
            expect(DB::table('billing_canary')->sole()->value)->toBe(42);
            expect(Activity::forSubject($sequence)->where('event', 'saas_sequence_initialized')->count())->toBe(1);
            expect(BillingSequence::count())->toBe(1);
        });
    } finally {
        tenancy()->end();
        DB::purge('tenant');
        TestCase::dropIsolatedMysqlDatabase($database);
        DB::connection()->beginTransaction();
    }
});
