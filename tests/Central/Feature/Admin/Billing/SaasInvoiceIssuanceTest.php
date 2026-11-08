<?php

use App\Enums\Central\Billing\DeliveryStatusEnum;
use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\PaymentStateEnum;
use App\Enums\Central\Billing\TransferStatusEnum;
use App\Enums\Central\Billing\TriggerEventEnum;
use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\BillingSequence;
use App\Models\Central\Billing\SaasDocumentDelivery;
use App\Models\Central\Billing\SaasInvoice;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Media\Media;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\Central\Tenants\Tenant;
use App\Models\User;
use App\Services\Central\Billing\BillingRuleService;
use App\Services\Central\Billing\InvoiceCalculator;
use App\Services\Central\Billing\InvoicePaymentService;
use App\Services\Central\Billing\SaasInvoiceIssuanceService;
use App\Services\Central\Billing\SaasInvoicePdfService;
use App\Services\Central\Billing\SaasInvoiceService;
use Database\Factories\Central\MediaFactory;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** @return array<string, mixed> */
function issuanceFixture(): array
{
    test()->seed(AuthorizationSeeder::class);
    $actor = User::factory()->create();
    $actor->assignRole(Role::findByName('root', 'central'));
    $owner = User::factory()->create();
    $owner->forceFill(['legal_address' => 'Adresse fiscale fictive', 'nif' => '123456789012345',
        'legal_verification_status' => VerificationStatusEnum::VERIFIED, 'legal_verified_at' => now()->subDay(), 'legal_verified_by_id' => $actor->id, 'legal_profile_version' => 1])->save();
    $subscription = Subscription::factory()->create(['user_id' => $owner->id, 'agreed_amount' => '3000.00',
        'period_starts_at' => now()->subDay(), 'period_ends_at' => now()->addMonth()]);
    $installment = new SubscriptionInstallment;
    $installment->forceFill(['user_id' => $owner->id, 'parent_subscription_id' => $subscription->id,
        'operation_key' => 'installment:test:'.Str::uuid(), 'installment_number' => 'TEST-'.Str::uuid(),
        'installment_amount' => '3000.00', 'due_at' => now()->addDays(10), 'installment_status' => InstallmentStatusEnum::PENDING,
        'period_starts_at' => $subscription->period_starts_at, 'period_ends_at' => $subscription->period_ends_at])->save();
    $installment->refresh();
    $rules = app(BillingRuleService::class);
    $rule = $rules->create($actor, ['code' => 'invoice.test.'.Str::lower(Str::random(8)),
        'trigger_event' => TriggerEventEnum::INSTALLMENT_DUE->value,
        'parameters' => ['document_type' => 1, 'payment_due_days' => 10]], (string) Str::uuid());
    $rules->validateRule($actor, $rule, $rule->managementState(), 'TEST-VALIDATION');
    $rule->refresh();
    $rules->activate($actor, $rule, $rule->managementState(), now()->subDay()->toDateTimeString(), null);
    $rule->refresh();
    config(['central.billing.customers' => [$owner->uuid => ['name' => trim($owner->first_name.' '.$owner->last_name),
        'address' => $owner->legal_address, 'nif' => $owner->getAttribute('nif'), 'validation_reference' => 'TEST-CUSTOMER', 'validated_at' => now()->subDay()->toISOString()]],
        'central.billing.issuer' => ['name' => 'Émetteur fictif', 'address' => 'Adresse fictive', 'nif' => '999999999999999',
            'validation_reference' => 'TEST-ISSUER', 'validated_at' => now()->subDay()->toISOString()],
        'central.billing.tax_policy' => ['validation_reference' => 'TEST-TAX', 'validated_at' => now()->subDay()->toISOString(),
            'no_tax_reason' => 'Fixture sans taxe explicitement définie', 'rates' => []]]);
    $disk = 'invoice-test-'.getmypid();
    Storage::fake($disk);
    config(['filesystems.disks.local' => ['driver' => 'local', 'root' => Storage::disk($disk)->path(''), 'throw' => true]]);
    Storage::forgetDisk('local');

    return compact('actor', 'owner', 'subscription', 'installment', 'rule');
}

/** @param array<string, mixed> $fixture */
function issuanceDraft(array $fixture): SaasInvoice
{
    return app(SaasInvoiceService::class)->prepareInstallment($fixture['actor'], $fixture['subscription'], $fixture['installment'], $fixture['rule']->uuid, $fixture['installment']->managementState());
}

test('full period invoicing refuses fractional or partially paid obligations', function (string $amount, InstallmentStatusEnum $status): void {
    $f = issuanceFixture();
    $f['installment']->forceFill(['installment_amount' => $amount, 'installment_status' => $status])->save();
    $f['installment']->refresh();

    expect(fn () => issuanceDraft($f))->toThrow(ValidationException::class);

    expect(SaasInvoice::count())->toBe(0)->and(BillingSequence::count())->toBe(0);
})->with([
    'fractional' => ['1000.00', InstallmentStatusEnum::PENDING],
    'partially paid' => ['3000.00', InstallmentStatusEnum::PARTIALLY_PAID],
]);

test('another obligation for the same period prevents a second invoice', function (): void {
    $f = issuanceFixture();
    $duplicate = $f['installment']->replicate(['uuid', 'operation_key', 'installment_number', 'parent_record_type', 'active_owner_slot']);
    $duplicate->forceFill(['operation_key' => 'installment:test:'.Str::uuid(), 'installment_number' => 'TEST-'.Str::uuid()])->save();

    expect(fn () => issuanceDraft($f))->toThrow(ValidationException::class);

    expect(SubscriptionInstallment::count())->toBe(2)->and(SaasInvoice::count())->toBe(0);
});

test('invoice calculations use exact decimals across multiple lines', function (): void {
    $result = app(InvoiceCalculator::class)->calculate([
        ['description' => 'A', 'quantity' => '3.00', 'net_unit_price' => '0.10', 'net_discount' => '0.00'],
        ['description' => 'B', 'quantity' => '2.00', 'net_unit_price' => '100.00', 'net_discount' => '10.00'],
    ], [['code' => 'TEST', 'rate' => '10.00']]);
    expect($result['net_amount'])->toBe('190.30')
        ->and($result['tax_amount'])->toBe('19.03')
        ->and($result['total_amount'])->toBe('209.33');
});

test('payment state is derived without changing the issued invoice or PDF', function (TransferStatusEnum $status, string $amount, PaymentStateEnum $state, ?string $secondAmount = null): void {
    $f = issuanceFixture();
    $draft = issuanceDraft($f);
    $invoice = app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $draft, $draft->managementState());
    $before = $invoice->getAttributes();
    $hash = $invoice->media->file_hash;
    $subscriptionState = $f['subscription']->refresh()->transitionState();
    $payment = new SaasPayment;
    $payment->forceFill(['user_id' => $invoice->user_id, 'document_id' => $invoice->id,
        'record_type' => SaasPayment::RECORD_TYPE, 'transfer_method' => 4,
        'transfer_status' => TransferStatusEnum::DECLARED, 'amount' => $amount, 'currency' => $invoice->currency,
        'created_by_id' => $f['actor']->id, 'operation_key' => 'saas:payment:test:'.Str::uuid(), 'correlation_id' => Str::uuid()])->save();
    $proof = Media::factory()->create(['model_type' => 'saas_payment', 'model_id' => $payment->getKey(),
        'created_by_id' => $f['actor']->id, 'collection_name' => 'payment_proof']);
    Storage::disk('local')->put($proof->storage_key, MediaFactory::PDF_CONTENT);
    $payment->forceFill(['transfer_status' => $status, 'proof_media_id' => $proof->id,
        'transfer_reference' => 'FIXTURE-'.Str::uuid(), 'financial_account_key' => 'fixture-account',
        'transaction_fingerprint' => hash('sha256', $payment->getAttribute('uuid')),
        'occurred_at' => now(), 'validated_by_id' => $status === TransferStatusEnum::VERIFIED ? $f['actor']->id : null,
        'validated_at' => $status === TransferStatusEnum::VERIFIED ? now() : null])->save();
    if ($secondAmount !== null) {
        $second = $payment->replicate(['uuid', 'operation_key', 'proof_media_id', 'transaction_fingerprint']);
        $second->forceFill(['transfer_status' => TransferStatusEnum::DECLARED, 'amount' => $secondAmount,
            'operation_key' => 'saas:payment:test:'.Str::uuid()])->save();
        $secondProof = Media::factory()->create(['model_type' => 'saas_payment', 'model_id' => $second->getKey(),
            'created_by_id' => $f['actor']->id, 'collection_name' => 'payment_proof']);
        Storage::disk('local')->put($secondProof->storage_key, MediaFactory::PDF_CONTENT);
        $second->forceFill(['transfer_status' => TransferStatusEnum::VERIFIED, 'proof_media_id' => $secondProof->id,
            'transaction_fingerprint' => hash('sha256', $second->getAttribute('uuid'))])->save();
    }

    $payments = app(InvoicePaymentService::class);
    expect($payments->states($f['owner'], collect([$invoice]))[$invoice->id])->toBe($state)
        ->and($payments->confirmedPayment($f['owner'], $invoice)?->getKey())->toBe($state === PaymentStateEnum::PAID ? $payment->getKey() : null);
    $this->actingAs($f['owner'], 'central')->get(route('owner.invoices.show', $invoice))->assertOk()->assertSee($state->label());

    expect($invoice->refresh()->getAttributes())->toBe($before)
        ->and($invoice->media->file_hash)->toBe($hash)
        ->and(hash('sha256', Storage::disk('local')->get($invoice->media->storage_key)))->toBe($hash)
        ->and($f['subscription']->refresh()->transitionState())->toBe($subscriptionState);
})->with([
    'declared' => [TransferStatusEnum::DECLARED, '3000.00', PaymentStateEnum::REVIEW],
    'verified full' => [TransferStatusEnum::VERIFIED, '3000.00', PaymentStateEnum::PAID],
    'verified insufficient' => [TransferStatusEnum::VERIFIED, '1000.00', PaymentStateEnum::ANOMALY],
    'rejected' => [TransferStatusEnum::REJECTED, '3000.00', PaymentStateEnum::ANOMALY],
    'two partial transfers are not combined' => [TransferStatusEnum::VERIFIED, '1000.00', PaymentStateEnum::ANOMALY, '2000.00'],
]);

test('T19-01 invoice emission creates a real private PDF and atomic pending delivery', function (): void {
    $f = issuanceFixture();
    $invoice = issuanceDraft($f);
    $this->actingAs($f['actor'], 'central')->post(route('central.invoices.issue', $invoice), ['expected_state' => $invoice->managementState()])
        ->assertSessionHasNoErrors()->assertRedirect();
    $invoice->refresh();
    expect($invoice->status)->toBe(DocumentStatusEnum::ISSUED)->and($invoice->total_amount)->toBe('3000.00');
    expect(app(InvoicePaymentService::class)->states($f['owner'], collect([$invoice]))[$invoice->id])->toBe(PaymentStateEnum::DUE)
        ->and(app(InvoicePaymentService::class)->confirmedPayment($f['owner'], $invoice))->toBeNull()
        ->and(SaasPayment::count())->toBe(0);
    $media = $invoice->media;
    expect(Storage::disk('local')->get($media->storage_key))->toStartWith('%PDF-');
    expect(hash('sha256', Storage::disk('local')->get($media->storage_key)))->toBe($media->file_hash);
    expect($media->storage_key)->toBe(app(SaasInvoicePdfService::class)->storageKey($invoice));
    $delivery = SaasDocumentDelivery::sole();
    expect($delivery->getAttribute('delivery_status'))->toBe(DeliveryStatusEnum::PENDING)
        ->and($delivery->getAttribute('attempts_count'))->toBe(0)
        ->and($delivery->getAttribute('encrypted_recipient'))->toBe($f['owner']->email)
        ->and($delivery->getRawOriginal('encrypted_recipient'))->not->toBe($f['owner']->email)
        ->and($delivery->getAttribute('sent_at'))->toBeNull();
    expect(Activity::forSubject($invoice)->where('event', SaasInvoiceIssuanceService::EVENT_ISSUED)->count())->toBe(1);
});

test('T19-02 an installment belonging to another subscription is refused without consuming a number', function (): void {
    $f = issuanceFixture();
    $other = Subscription::factory()->create();
    expect(fn () => app(SaasInvoiceService::class)->prepareInstallment($f['actor'], $other, $f['installment'], $f['rule']->uuid, $f['installment']->managementState()))->toThrow(ValidationException::class);
    expect(SaasInvoice::count())->toBe(0)->and(BillingSequence::count())->toBe(0);
});

test('T19-03 missing fiscal proof or inactive rule blocks preparation', function (string $invalid): void {
    $f = issuanceFixture();
    if ($invalid === 'rule') {
        $f['rule']->forceFill(['ends_at' => now()->subHour()])->save();
    } elseif ($invalid === 'issuer') {
        config(['central.billing.issuer.nif' => null]);
    } elseif ($invalid === 'policy') {
        config(['central.billing.tax_policy.validation_reference' => null]);
    } else {
        $f['owner']->forceFill(['nif' => null])->save();
    }
    expect(fn () => issuanceDraft($f))->toThrow(ValidationException::class);
    expect(SaasInvoice::count())->toBe(0)->and(BillingSequence::count())->toBe(0);
})->with(['issuer', 'policy', 'customer', 'rule']);

test('T19-04 tax included calculation preserves the installment total and exact cents', function (): void {
    $f = issuanceFixture();
    config(['central.billing.tax_policy.rates' => [['code' => 'TEST-TAX', 'rate' => '10.00']]]);
    $invoice = issuanceDraft($f);
    expect($invoice->total_amount)->toBe('3000.00')->and($invoice->net_amount)->toBe('2727.27')->and($invoice->tax_amount)->toBe('272.73');
    $issued = app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $invoice, $invoice->managementState());
    expect($issued->status)->toBe(DocumentStatusEnum::ISSUED);
});

test('T19-06 replay preserves document lines number PDF and delivery intent', function (): void {
    $f = issuanceFixture();
    $invoice = issuanceDraft($f);
    $expected = $invoice->managementState();
    $issued = app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $invoice, $expected);
    $hash = $issued->media->file_hash;
    expect(issuanceDraft($f)->uuid)->toBe($issued->uuid);
    $repeated = app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $invoice, $expected);
    expect($repeated->number)->toBe($issued->number)->and($repeated->media->file_hash)->toBe($hash)
        ->and(SaasInvoice::count())->toBe(1)->and($repeated->lines()->count())->toBe(1)
        ->and(SaasDocumentDelivery::count())->toBe(1)->and(BillingSequence::sole()->next_number)->toBe(2);
});

test('T19-07 and T19-08 PDF failure preserves the number and a retry completes the same invoice', function (): void {
    $f = issuanceFixture();
    $invoice = issuanceDraft($f);
    $broken = Mockery::mock(SaasInvoicePdfService::class);
    $broken->shouldReceive('generate')->once()->andThrow(new RuntimeException('Simulated storage failure'));
    $this->app->instance(SaasInvoicePdfService::class, $broken);
    expect(fn () => app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $invoice, $invoice->managementState()))->toThrow(ValidationException::class);
    $invoice->refresh();
    expect($invoice->status)->toBe(DocumentStatusEnum::PREPARING)->and(SaasDocumentDelivery::count())->toBe(0);
    $number = $invoice->number;
    $this->app->forgetInstance(SaasInvoicePdfService::class);
    $issued = app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $invoice, $invoice->managementState());
    expect($issued->status)->toBe(DocumentStatusEnum::ISSUED)->and($issued->number)->toBe($number)
        ->and(BillingSequence::sole()->next_number)->toBe(2);
});

test('T19-09 emitted financial content and lines are immutable in MySQL', function (): void {
    $f = issuanceFixture();
    $invoice = issuanceDraft($f);
    $invoice = app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $invoice, $invoice->managementState());
    expect(fn () => $invoice->forceFill(['total_amount' => '1.00'])->save())->toThrow(QueryException::class);
    expect(fn () => $invoice->lines()->sole()->forceFill(['description' => 'Changed'])->save())->toThrow(QueryException::class);
    expect($invoice->refresh()->total_amount)->toBe('3000.00');
});

test('T19-10 profile changes do not rewrite historical snapshots or PDF bytes', function (): void {
    $f = issuanceFixture();
    $invoice = issuanceDraft($f);
    $invoice = app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $invoice, $invoice->managementState());
    $snapshot = $invoice->customer_identity_snapshot;
    $hash = $invoice->media->file_hash;
    $f['owner']->forceFill(['first_name' => 'Updated', 'legal_address' => 'Nouvelle adresse'])->save();
    expect($invoice->refresh()->customer_identity_snapshot)->toBe($snapshot)
        ->and(hash('sha256', Storage::disk('local')->get($invoice->media->storage_key)))->toBe($hash);
});

test('T19-11 another merchant cannot download an invoice even with its signed URL', function (): void {
    $f = issuanceFixture();
    $invoice = issuanceDraft($f);
    $invoice = app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $invoice, $invoice->managementState());
    $url = URL::temporarySignedRoute('owner.invoices.download', now()->addMinutes(5), ['invoice' => $invoice]);
    $this->actingAs(User::factory()->create(), 'central')->get($url)->assertForbidden();

    $this->actingAs($f['owner'], 'central')
        ->get(URL::temporarySignedRoute('owner.invoices.download', now()->subMinute(), ['invoice' => $invoice]))
        ->assertForbidden();

    $this->actingAs($f['actor'], 'central')->get(URL::temporarySignedRoute('central.invoices.download', now()->addMinutes(5), ['invoice' => $invoice]))->assertOk();

    $draftFixture = issuanceFixture();
    $draft = issuanceDraft($draftFixture);
    $this->actingAs($draftFixture['owner'], 'central')
        ->get(URL::temporarySignedRoute('owner.invoices.download', now()->addMinutes(5), ['invoice' => $draft]))
        ->assertForbidden();

    Storage::disk('local')->put($invoice->media->storage_key, 'Altered');
    expect(fn () => app(SaasInvoiceIssuanceService::class)->download($f['owner'], $invoice))->toThrow(ValidationException::class);
});

test('staff without invoice rights cannot prepare emit read or download and consume no counter', function (): void {
    $f = issuanceFixture();
    $invoice = issuanceDraft($f);
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('non-billing', ['saas.users.view' => 7]));
    $this->actingAs($actor, 'central')->get(route('central.invoices.show', $invoice))->assertForbidden();
    $this->post(route('central.invoices.issue', $invoice), ['expected_state' => $invoice->managementState()])->assertForbidden();
    $this->post(route('central.invoices.installment.store', ['subscription' => $f['subscription'], 'installment' => $f['installment']]), ['billing_rule_uuid' => $f['rule']->uuid])->assertForbidden();
    $this->get(URL::temporarySignedRoute('central.invoices.download', now()->addMinutes(5), ['invoice' => $invoice]))->assertForbidden();
    expect(BillingSequence::count())->toBe(0);
});

test('T19-12 two independent processes emit the same operation only once', function (bool $same): void {
    $first = issuanceFixture();
    $a = issuanceDraft($first);
    $b = $same ? $a : issuanceDraft(issuanceFixture());
    $database = DB::connection()->getDatabaseName();
    expect($database)->toStartWith('aydra_test_');
    $c = config('database.connections.mysql');
    $environment = ['APP_ENV' => 'testing', 'DB_URL' => false, 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database,
        'DB_HOST' => $c['host'], 'DB_PORT' => (string) $c['port'], 'DB_USERNAME' => $c['username'], 'DB_PASSWORD' => $c['password'],
        'CACHE_STORE' => 'array', 'INVOICE_ACTOR' => $first['actor']->uuid, 'INVOICE_STORAGE_ROOT' => Storage::disk('local')->path('')];
    $script = <<<'PHP'
    require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $c=config('database.connections.mysql');
    if(!in_array($c['host'],['127.0.0.1','localhost','::1'],true)||!str_starts_with($c['database'],'aydra_test_')||!empty($c['url'])){throw new LogicException('Isolated local database required.');}
    config(['filesystems.disks.local'=>['driver'=>'local','root'=>getenv('INVOICE_STORAGE_ROOT'),'throw'=>true]]);
    $actor=App\Models\User::where('uuid',getenv('INVOICE_ACTOR'))->firstOrFail();
    $invoice=App\Models\Central\Billing\SaasInvoice::where('uuid',getenv('INVOICE_SUBJECT'))->firstOrFail();
    $result=app(App\Services\Central\Billing\SaasInvoiceIssuanceService::class)->issue($actor,$invoice,$invoice->managementState());
    echo json_encode(['uuid'=>$result->uuid,'number'=>$result->number]);
    PHP;
    DB::commit();
    $processes = [];
    try {
        foreach ([$a, $b] as $invoice) {
            $process = new Process([PHP_BINARY, '-r', $script], base_path(), [...$environment, 'INVOICE_SUBJECT' => $invoice->uuid]);
            $process->setTimeout(60)->start();
            $processes[] = $process;
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
            $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }
        expect($results[0]['number'] === $results[1]['number'])->toBe($same);
        expect(SaasInvoice::where('status', DocumentStatusEnum::ISSUED)->count())->toBe($same ? 1 : 2)
            ->and(SaasDocumentDelivery::count())->toBe($same ? 1 : 2)
            ->and(BillingSequence::sole()->next_number)->toBe($same ? 2 : 3);
    } finally {
        foreach ($processes as $process) {
            $process->stop();
        }
        DB::beginTransaction();
    }
})->with([true, false]);

test('a crash after a PDF is stored resumes using exactly its original bytes', function (): void {
    $f = issuanceFixture();
    $invoice = issuanceDraft($f);
    $issuance = app(SaasInvoiceIssuanceService::class);
    $dispatcher = (new SaasDocumentDelivery)->getEventDispatcher();
    $event = 'eloquent.creating: '.SaasDocumentDelivery::class;
    $listeners = $dispatcher->getRawListeners()[$event] ?? [];
    SaasDocumentDelivery::creating(function (): void {
        throw new RuntimeException('Interrupted finalization');
    });
    try {
        expect(fn () => $issuance->issue($f['actor'], $invoice, $invoice->managementState()))->toThrow(ValidationException::class);
    } finally {
        $dispatcher->forget($event);
        foreach ($listeners as $listener) {
            $dispatcher->listen($event, $listener);
        }
    }
    $invoice->refresh();
    expect($invoice->status)->toBe(DocumentStatusEnum::PREPARING)->and(Media::count())->toBe(0);
    $key = app(SaasInvoicePdfService::class)->storageKey($invoice);
    $bytes = Storage::disk('local')->get($key);
    $issued = $issuance->issue($f['actor'], $invoice, $invoice->managementState());
    expect($issued->status)->toBe(DocumentStatusEnum::ISSUED)
        ->and(Storage::disk('local')->get($key))->toBe($bytes)->and(SaasDocumentDelivery::count())->toBe(1);
});

test('reserved invoice media cannot be changed or archived through SQL', function (): void {
    $f = issuanceFixture();
    $invoice = issuanceDraft($f);
    $invoice = app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $invoice, $invoice->managementState());
    foreach (['file_hash' => str_repeat('a', 64), 'storage_key' => 'other.pdf', 'deleted_at' => now()] as $field => $value) {
        expect(fn () => DB::table('media')->where('id', $invoice->getAttribute('document_media_id'))->update([$field => $value]))->toThrow(QueryException::class);
    }
});

test('invoice forms enforce server values while owner views and filters remain correctly scoped', function (): void {
    $f = issuanceFixture();
    $route = route('central.invoices.installment.store', ['subscription' => $f['subscription'], 'installment' => $f['installment']]);
    $this->actingAs($f['actor'], 'central')->post($route, ['billing_rule_uuid' => $f['rule']->uuid,
        'expected_state' => $f['installment']->managementState(), 'total_amount' => '1.00'])->assertSessionHasErrors('total_amount');
    $this->post($route, ['billing_rule_uuid' => $f['rule']->uuid, 'expected_state' => $f['installment']->managementState()])
        ->assertSessionHasNoErrors()->assertRedirect();
    $invoice = SaasInvoice::sole();
    $this->get(route('central.invoices.show', $invoice))->assertOk()->assertSee('3000.00')->assertSee('Émettre la facture');
    $this->get(route('central.invoices.index', ['status' => DocumentStatusEnum::ISSUED->value]))->assertOk()->assertSee('Aucune facture préparée.');
    $this->actingAs($f['owner'], 'central')->get(route('owner.invoices.index'))->assertOk()->assertSee('3000.00');
    $alias = Route::has('central.alias.0.owner.invoices.index')
        ? 'central.alias.0.owner.invoices.index' : 'central.alias.1.owner.invoices.index';
    $this->get(route($alias))->assertOk()->assertSee('3000.00');
    $this->get(route('owner.invoices.show', $invoice))->assertOk()->assertDontSee('Émettre la facture');
    $this->get(route('central.invoices.show', $invoice))->assertForbidden();
    $this->actingAs(User::factory()->create(), 'central')->get(route('owner.invoices.show', $invoice))->assertForbidden();
    expect(fn () => app(InvoicePaymentService::class)->confirmedPayment(User::factory()->create(), $invoice))
        ->toThrow(AuthorizationException::class);
});

test('invalid line data or stale forms cannot reserve a number', function (string $invalid): void {
    $f = issuanceFixture();
    $invoice = issuanceDraft($f);
    $expected = $invoice->managementState();
    if ($invalid === 'missing-line') {
        $invoice->lines()->delete();
    } elseif ($invalid === 'tax') {
        $line = $invoice->lines()->sole();
        $line->forceFill(['taxes' => [['code' => 'FAKE', 'rate' => '999.00', 'amount' => '0.00']]])->save();
    } elseif ($invalid === 'total') {
        $invoice->lines()->sole()->forceFill(['net_unit_price' => '1.00'])->save();
    } else {
        $expected = str_repeat('0', 64);
    }
    expect(fn () => app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $invoice, $expected))->toThrow(ValidationException::class);
    expect(BillingSequence::count())->toBe(0)->and($invoice->refresh()->status)->toBe(DocumentStatusEnum::DRAFT);
})->with(['missing-line', 'tax', 'total', 'stale']);

test('invoice services refuse an initialized shop context without touching its database', function (): void {
    $f = issuanceFixture();
    $invoice = issuanceDraft($f);
    Event::fake([TenantCreated::class]);
    $tenant = Tenant::factory()->create(['slug' => 'invoice-'.Str::lower(Str::random(8))]);
    $database = $tenant->database()->getName();
    expect($database)->toStartWith(config('tenancy.database.prefix'));
    DB::commit();
    try {
        CreateDatabase::dispatchSync($tenant);
        $tenant->run(function () use ($f, $invoice): void {
            DB::statement('CREATE TABLE invoice_canary (id INT PRIMARY KEY, value INT NOT NULL)');
            DB::table('invoice_canary')->insert(['id' => 1, 'value' => 42]);
            expect(fn () => app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $invoice, $invoice->managementState()))
                ->toThrow(HttpException::class);
            expect(fn () => issuanceDraft($f))->toThrow(HttpException::class);
            expect(fn () => app(InvoicePaymentService::class)->confirmedPayment($f['owner'], $invoice))->toThrow(HttpException::class);
            expect(DB::table('invoice_canary')->sole()->value)->toBe(42);
        });
        expect(BillingSequence::count())->toBe(0);
    } finally {
        tenancy()->end();
        DB::purge('tenant');
        TestCase::dropIsolatedMysqlDatabase($database);
        DB::beginTransaction();
    }
});

test('browser emits a real isolated invoice and its owner can read and download it', function (): void {
    $f = issuanceFixture();
    $invoice = issuanceDraft($f);
    $database = DB::connection()->getDatabaseName();
    expect($database)->toStartWith('aydra_test_');
    $runtime = storage_path('framework/testing/module19-browser-'.Str::uuid());
    foreach (['framework/views', 'framework/cache/data', 'framework/sessions', 'logs', 'app/private'] as $directory) {
        File::ensureDirectoryExists($runtime.'/'.$directory);
    }
    config(['filesystems.disks.local.root' => $runtime.'/app/private']);
    Storage::forgetDisk('local');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $error, $message);
    expect($socket)->not->toBeFalse();
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int) substr(strrchr($address, ':'), 1);
    $base = 'http://127.0.0.1:'.$port;
    $c = config('database.connections.mysql');
    $environment = ['APP_ENV' => 'testing', 'APP_URL' => $base, 'SAAS_BASE_DOMAIN' => '127.0.0.1',
        'DB_CONNECTION' => 'mysql', 'DB_URL' => false, 'DB_DATABASE' => $database,
        'DB_HOST' => $c['host'], 'DB_PORT' => (string) $c['port'], 'DB_USERNAME' => $c['username'], 'DB_PASSWORD' => $c['password'],
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'file', 'SESSION_SECURE_COOKIE' => 'false', 'SESSION_DOMAIN' => '(null)',
        'SESSION_COOKIE' => 'module19_'.Str::random(12), 'LARAVEL_STORAGE_PATH' => $runtime, 'MAIL_MAILER' => 'log',
        'APP_CONFIG_CACHE' => $runtime.'/config.php', 'APP_ROUTES_CACHE' => $runtime.'/routes.php'];
    DB::commit();
    $server = new Process([PHP_BINARY, 'artisan', 'serve', '--host=127.0.0.1', '--port='.$port, '--no-reload', '--no-interaction'], base_path(), $environment);
    $server->setTimeout(null);
    try {
        $server->start();
        File::put($runtime.'/ready.json', json_encode(['url' => $base, 'invoice' => $invoice->uuid,
            'admin' => $f['actor']->email, 'owner' => $f['owner']->email], JSON_THROW_ON_ERROR));
        $deadline = microtime(true) + 900;
        while (! is_file($runtime.'/complete.json') && microtime(true) < $deadline && $server->isRunning()) {
            usleep(200000);
        }
        expect(is_file($runtime.'/complete.json'))->toBeTrue($server->getErrorOutput());
        expect($invoice->refresh()->status)->toBe(DocumentStatusEnum::ISSUED);
        expect(Storage::disk('local')->get($invoice->media->storage_key))->toStartWith('%PDF-');
        expect(SaasDocumentDelivery::count())->toBe(1);
    } finally {
        $server->stop();
        DB::beginTransaction();
        File::deleteDirectory($runtime);
    }
})->skip(fn (): bool => getenv('AYDRA_MODULE19_BROWSER') !== '1', 'Requires an interactive browser pilot on isolated data.');
