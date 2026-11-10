<?php

require_once dirname(__DIR__, 4).'/Support/ManualBillingFixtures.php';

use App\Enums\Central\Billing\TransferStatusEnum;
use App\Enums\Central\Subscriptions\InstallmentStatusEnum;
use App\Enums\Central\Subscriptions\PaymentChoiceEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Media\Media;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Plans\Plan;
use App\Models\Central\Roles\Role;
use App\Models\Central\Subscriptions\Subscription;
use App\Models\Central\Subscriptions\SubscriptionInstallment;
use App\Models\Central\Tenants\Tenant;
use App\Models\User;
use App\Services\Central\Billing\PaymentDocumentService;
use App\Services\Central\Billing\SaasInvoiceIssuanceService;
use App\Services\Central\Billing\SaasInvoiceService;
use App\Services\Central\Billing\SaasPaymentService;
use App\Services\Central\Plans\PublicPlanCatalog;
use App\Services\Central\Subscriptions\DefaultSubscription;
use App\Services\Central\Subscriptions\SubscriptionAssignmentService;
use App\Services\Central\Subscriptions\SubscriptionPurchaseService;
use Database\Factories\Central\MediaFactory;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\FreePlanSeeder;
use Database\Seeders\Central\PaymentPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

test('T21-01 cash payment settles the full period exactly once without changing the invoice', function (): void {
    $f = manualPaymentFixture();
    $invoiceState = $f['invoice']->getAttributes();
    $data = ['amount' => '3000.00', 'currency' => 'DZD', 'occurred_at' => now()->toDateTimeString(),
        'operation_id' => (string) Str::uuid(), 'received' => true];

    $payment = app(SaasPaymentService::class)->recordCashPayment($f['actor'], $f['invoice'], $data);
    $replay = app(SaasPaymentService::class)->recordCashPayment($f['actor'], $f['invoice'], $data);

    expect($payment->getAttribute('transfer_status'))->toBe(TransferStatusEnum::VERIFIED)
        ->and($payment->getAttribute('transfer_reference'))->toStartWith('CASH-')
        ->and($replay->getKey())->toBe($payment->getKey())->and(SaasPayment::count())->toBe(1)
        ->and($f['due']->refresh()->installment_status)->toBe(InstallmentStatusEnum::PAID)
        ->and($f['subscription']->refresh()->status)->toBe(StatusEnum::ACTIVE)
        ->and($f['invoice']->refresh()->getAttributes())->toBe($invoiceState);
});

test('T21-02 and T21-07 real cash anomalies remain traceable without paying the period', function (string $amount): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->recordCashPayment($f['actor'], $f['invoice'], [
        ...manualExternalPayload($amount), 'received' => true]);

    expect($payment->getAttribute('amount'))->toBe($amount)
        ->and($payment->getAttribute('transfer_status'))->toBe(TransferStatusEnum::UNCERTAIN)
        ->and($payment->getAttribute('proof_media_id'))->not->toBeNull()
        ->and($f['due']->refresh()->installment_status)->toBe(InstallmentStatusEnum::PENDING)
        ->and($f['subscription']->refresh()->status)->toBe(StatusEnum::SCHEDULED)
        ->and(Activity::forSubject($payment)->where('event', SaasPaymentService::ANOMALY)->count())->toBe(1);
})->with(['1000.00', '3500.00']);

test('T21-03 and T21-04 external proof is private and requires actual full confirmation', function (): void {
    $f = manualPaymentFixture();
    $data = manualExternalPayload();
    $file = UploadedFile::fake()->createWithContent('preuve.pdf', MediaFactory::PDF_CONTENT);
    $this->actingAs($f['owner'], 'central')->post(route('owner.payments.store', $f['invoice']), [...$data, 'proof' => $file])
        ->assertSessionHasNoErrors()->assertRedirect();
    $payment = SaasPayment::sole();
    expect($payment->getAttribute('transfer_status'))->toBe(TransferStatusEnum::DECLARED)
        ->and($f['subscription']->refresh()->status)->toBe(StatusEnum::SCHEDULED);
    $this->get(route('owner.payments.show', $payment))->assertOk()->assertSee('À vérifier')->assertDontSee('Confirmer la réception intégrale');
    $this->post(route('central.payments.verify', $payment), manualVerification($payment))->assertForbidden();
    $this->actingAs($f['actor'], 'central')->post(route('central.payments.verify', $payment), manualVerification($payment))
        ->assertSessionHasNoErrors()->assertRedirect();
    $payment->refresh();
    expect($payment->getAttribute('transfer_status'))->toBe(TransferStatusEnum::VERIFIED)
        ->and($f['due']->refresh()->installment_status)->toBe(InstallmentStatusEnum::PAID)
        ->and($f['subscription']->refresh()->status)->toBe(StatusEnum::ACTIVE);
    $this->actingAs($f['owner'], 'central')->get(URL::temporarySignedRoute('owner.payments.receipt', now()->addMinutes(5), ['payment' => $payment]))
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    expect($payment->documents()->where('collection_name', PaymentDocumentService::RECEIPT)->count())->toBe(1);
});

test('T21-05 proof alone and false confirmation never activate rights', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->declareExternalPayment($f['owner'], $f['invoice'], manualExternalPayload(),
        UploadedFile::fake()->createWithContent('preuve.pdf', MediaFactory::PDF_CONTENT));
    expect(fn () => app(SaasPaymentService::class)->verifyPayment($f['actor'], $payment, [...manualVerification($payment), 'received' => false]))
        ->toThrow(ValidationException::class);
    expect($payment->refresh()->getAttribute('transfer_status'))->toBe(TransferStatusEnum::DECLARED)
        ->and($f['subscription']->refresh()->status)->toBe(StatusEnum::SCHEDULED)
        ->and($payment->documents()->where('collection_name', PaymentDocumentService::RECEIPT)->exists())->toBeFalse();
});

test('T21-06 insufficient external declaration is an anomaly and cannot be completed in parts', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->declareExternalPayment($f['owner'], $f['invoice'], manualExternalPayload('2500.00'),
        UploadedFile::fake()->createWithContent('preuve.pdf', MediaFactory::PDF_CONTENT));
    expect(fn () => app(SaasPaymentService::class)->verifyPayment($f['actor'], $payment, manualVerification($payment)))->toThrow(ValidationException::class);
    expect($payment->refresh()->getAttribute('transfer_status'))->toBe(TransferStatusEnum::UNCERTAIN)
        ->and($f['due']->refresh()->installment_status)->toBe(InstallmentStatusEnum::PENDING);
});

test('T21-08 invalid cash amounts never create a financial entry', function (string $amount): void {
    $f = manualPaymentFixture();
    expect(fn () => app(SaasPaymentService::class)->recordCashPayment($f['actor'], $f['invoice'], [
        ...manualExternalPayload($amount), 'received' => true]))->toThrow(ValidationException::class);
    expect(SaasPayment::count())->toBe(0);
})->with(['0.00', '-1.00', 'not-money']);

test('T21-09 and T21-17 foreign owners cannot declare or download another owners evidence', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->recordCashPayment($f['actor'], $f['invoice'], [...manualExternalPayload(), 'received' => true]);
    $this->actingAs(User::factory()->create(), 'central')->get(route('owner.payments.create', $f['invoice']))->assertForbidden();
    $this->get(route('owner.payments.show', $payment))->assertForbidden();
    $this->get(URL::temporarySignedRoute('owner.payments.receipt', now()->addMinutes(5), ['payment' => $payment]))->assertForbidden();
    $this->get(URL::temporarySignedRoute('owner.payments.proof', now()->addMinutes(5), ['payment' => $payment]))->assertForbidden();
});

test('T21-11 identical external declarations replay without another payment or evidence', function (): void {
    $f = manualPaymentFixture();
    $data = manualExternalPayload();
    $declare = fn (array $payload) => app(SaasPaymentService::class)->declareExternalPayment($f['owner'], $f['invoice'], $payload,
        UploadedFile::fake()->createWithContent('preuve.pdf', MediaFactory::PDF_CONTENT));
    $a = $declare($data);
    $b = $declare([...$data, 'operation_id' => (string) Str::uuid()]);
    expect($a->getKey())->toBe($b->getKey())->and(SaasPayment::count())->toBe(1)->and($a->documents()->count())->toBe(1);
});

test('T21-14 altered evidence blocks verification without losing the declaration', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->declareExternalPayment($f['owner'], $f['invoice'], manualExternalPayload(),
        UploadedFile::fake()->createWithContent('preuve.pdf', MediaFactory::PDF_CONTENT));
    $media = Media::whereKey($payment->getAttribute('proof_media_id'))->firstOrFail();
    Storage::disk('local')->put($media->storage_key, 'tampered');
    expect(fn () => app(SaasPaymentService::class)->verifyPayment($f['actor'], $payment, manualVerification($payment)))->toThrow(ValidationException::class);
    expect($payment->refresh()->getAttribute('transfer_status'))->toBe(TransferStatusEnum::DECLARED)
        ->and($f['subscription']->refresh()->status)->toBe(StatusEnum::SCHEDULED);
});

test('external rejection requires a reason and preserves evidence and administrative history', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->declareExternalPayment($f['owner'], $f['invoice'], manualExternalPayload(),
        UploadedFile::fake()->createWithContent('preuve.pdf', MediaFactory::PDF_CONTENT));
    $this->actingAs($f['actor'], 'central')->post(route('central.payments.reject', $payment), ['expected_state' => $payment->managementState(), 'reason' => ''])
        ->assertSessionHasErrors('reason');
    $this->post(route('central.payments.reject', $payment), ['expected_state' => $payment->managementState(), 'reason' => 'Fonds non reçus'])
        ->assertSessionHasNoErrors();
    $this->actingAs($f['owner'], 'central')->get(route('owner.payments.show', $payment))->assertOk()->assertSee('Fonds non reçus')->assertDontSee('Historique');
    expect($payment->refresh()->getAttribute('transfer_status'))->toBe(TransferStatusEnum::REJECTED)
        ->and(Activity::forSubject($payment)->where('event', SaasPaymentService::REJECTED)->count())->toBe(1);
});

test('T21-19 an unpaid invoiced period never activates through subscription resolution', function (): void {
    $f = manualPaymentFixture();
    $this->seed(FreePlanSeeder::class);

    $effective = app(DefaultSubscription::class)->ensure($f['owner']);

    expect($effective->plan->code)->toBe('free')->and($f['subscription']->refresh()->status)->toBe(StatusEnum::SCHEDULED);
});

test('T21-18 a paid future period preserves current rights and activates only at its real start', function (): void {
    $f = manualPaymentFixture(future: true);
    $this->seed(FreePlanSeeder::class);
    $defaults = app(DefaultSubscription::class);
    $free = $defaults->ensure($f['owner']);
    $payment = app(SaasPaymentService::class)->recordCashPayment($f['actor'], $f['invoice'], [...manualExternalPayload(), 'received' => true]);
    expect($f['subscription']->refresh()->status)->toBe(StatusEnum::SCHEDULED)
        ->and($defaults->ensure($f['owner'])->getKey())->toBe($free->getKey());

    $this->travel(3)->days();
    $effective = $defaults->ensure($f['owner']);

    expect($effective->getKey())->toBe($f['subscription']->getKey())
        ->and($effective->status)->toBe(StatusEnum::ACTIVE)
        ->and($payment->refresh()->getAttribute('transfer_status'))->toBe(TransferStatusEnum::VERIFIED);
});

test('new administrative paid assignments wait for payment without granting premium rights', function (): void {
    $f = manualPaymentFixture();
    $owner = User::factory()->create();
    $plan = Plan::factory()->create(['is_active' => true, 'monthly_price' => '3000.00']);

    $assigned = app(SubscriptionAssignmentService::class)->assign($f['actor'], [
        'owner_uuid' => $owner->uuid, 'plan_uuid' => $plan->uuid, 'period' => 1, 'expected_amount' => '3000.00', 'operation_key' => (string) Str::uuid()]);

    expect($assigned->status)->toBe(StatusEnum::SCHEDULED)
        ->and(SubscriptionInstallment::where('subscription_id', $assigned->getKey())->sole()->installment_status)->toBe(InstallmentStatusEnum::PENDING);
    $this->actingAs($f['actor'], 'central')->get(route('central.subscriptions.show', $assigned))->assertOk()
        ->assertSee('ses droits attendent la confirmation du paiement intégral')->assertDontSee('Préparer l’échéance et la facture');
});

test('payment permission seeding preserves existing identities labels and assignments', function (): void {
    $this->seed(AuthorizationSeeder::class);
    $permission = Permission::findByName('saas.payments.validate', 'central');
    $permission->forceFill(['label' => 'Libellé conservé'])->save();
    $identity = $permission->only(['id', 'uuid', 'label']);
    $roles = DB::table('role_has_permissions')->get()->toArray();
    $direct = DB::table('model_has_permissions')->get()->toArray();
    $this->seed(PaymentPermissionSeeder::class);
    $this->seed(PaymentPermissionSeeder::class);
    expect($permission->refresh()->only(['id', 'uuid', 'label']))->toBe($identity)
        ->and(Permission::where('name', 'like', 'saas.payments.%')->count())->toBe(4)
        ->and(DB::table('role_has_permissions')->get()->toArray())->toEqual($roles)
        ->and(DB::table('model_has_permissions')->get()->toArray())->toEqual($direct);
});

test('malformed form values return validation errors without breaking the payment screens', function (): void {
    $f = manualPaymentFixture(purchase: true);
    $payment = app(SaasPaymentService::class)->declareExternalPayment($f['owner'], $f['invoice'], manualExternalPayload(),
        UploadedFile::fake()->createWithContent('preuve.pdf', MediaFactory::PDF_CONTENT));
    $this->actingAs($f['actor'], 'central')->post(route('central.payments.cash.store', $f['invoice']), [
        'amount' => [], 'occurred_at' => [], 'operation_id' => [], 'currency' => 'DZD', 'received' => true,
    ])->assertSessionHasErrors(['amount', 'occurred_at', 'operation_id']);
    $this->get(route('central.payments.cash.create', $f['invoice']))->assertOk();
    $this->post(route('central.payments.verify', $payment), [...manualVerification($payment), 'account' => [], 'reference' => []])
        ->assertSessionHasErrors(['account', 'reference']);
    $this->get(route('central.payments.show', $payment))->assertOk();
    $this->actingAs($f['owner'], 'central')->post(route('owner.subscriptions.store'), [
        'plan_uuid' => $f['subscription']->plan->uuid, 'operation_id' => [], 'period' => [], 'expected_amount' => [],
    ])->assertSessionHasErrors(['operation_id', 'period', 'expected_amount']);
    $this->get(route('owner.subscriptions.index'))->assertOk();
});

test('T21 end to end chosen Pro invoice payment and private receipt preserve exact dates', function (bool $cash): void {
    Http::fake();
    $f = manualPaymentFixture(purchase: true);
    $dates = $f['subscription']->only(['period_starts_at', 'period_ends_at']);
    $invoiceHash = $f['invoice']->media->file_hash;
    expect($f['subscription']->status)->toBe(StatusEnum::SCHEDULED);

    if ($cash) {
        $payment = app(SaasPaymentService::class)->recordCashPayment($f['actor'], $f['invoice'], [...manualExternalPayload(), 'received' => true]);
    } else {
        $payment = app(SaasPaymentService::class)->declareExternalPayment($f['owner'], $f['invoice'], manualExternalPayload(),
            UploadedFile::fake()->createWithContent('preuve.pdf', MediaFactory::PDF_CONTENT));
        expect($f['subscription']->refresh()->status)->toBe(StatusEnum::SCHEDULED);
        $payment = app(SaasPaymentService::class)->verifyPayment($f['actor'], $payment, manualVerification($payment));
        app(SaasPaymentService::class)->verifyPayment($f['actor'], $payment, manualVerification($payment));
    }

    expect($f['subscription']->refresh()->status)->toBe(StatusEnum::ACTIVE)
        ->and($f['subscription']->only(['period_starts_at', 'period_ends_at']))->toEqual($dates)
        ->and($f['invoice']->refresh()->media->file_hash)->toBe($invoiceHash)
        ->and(Activity::forSubject($payment)->where('event', SaasPaymentService::VERIFIED)->count())->toBe(1);
    $receipt = $payment->documents()->where('collection_name', PaymentDocumentService::RECEIPT)->sole();
    expect(Storage::disk('local')->get($receipt->storage_key))->toStartWith('%PDF-')
        ->and(hash('sha256', Storage::disk('local')->get($receipt->storage_key)))->toBe($receipt->file_hash);
    Http::assertNothingSent();
})->with([true, false]);

test('T21-10 all payment administrative endpoints reject staff without payment permissions', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->declareExternalPayment($f['owner'], $f['invoice'], manualExternalPayload(),
        UploadedFile::fake()->createWithContent('preuve.pdf', MediaFactory::PDF_CONTENT));
    $staff = User::factory()->create();
    $staff->assignRole(Role::createWithPermissions('payment-staff-'.Str::random(8), ['saas.users.view' => 7]));
    $this->actingAs($staff, 'central');
    $this->get(route('central.payments.index'))->assertForbidden();
    $this->get(route('central.payments.show', $payment))->assertForbidden();
    $this->get(route('central.payments.cash.create', $f['invoice']))->assertForbidden();
    $this->post(route('central.payments.cash.store', $f['invoice']), [])->assertForbidden();
    foreach (['verify', 'reject', 'reconcile'] as $action) {
        $this->post(route('central.payments.'.$action, $payment), [])->assertForbidden();
    }
    $this->get(URL::temporarySignedRoute('central.payments.proof', now()->addMinutes(5), ['payment' => $payment]))->assertForbidden();
    $this->get(URL::temporarySignedRoute('central.payments.receipt', now()->addMinutes(5), ['payment' => $payment]))->assertForbidden();
});

test('images are converted to a private PDF while their original evidence stays immutable', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->declareExternalPayment($f['owner'], $f['invoice'], manualExternalPayload(), UploadedFile::fake()->image('photo.png', 32, 32));
    expect($payment->getAttribute('source_proof_media_id'))->not->toBeNull();
    $proof = app(PaymentDocumentService::class)->assertProof($payment);
    expect(Storage::disk('local')->get($proof->storage_key))->toStartWith('%PDF-');
    expect(fn () => DB::table('media')->where('id', $payment->getAttribute('source_proof_media_id'))->update(['file_hash' => str_repeat('a', 64)]))
        ->toThrow(QueryException::class);
});

test('invalid uploads create no payment declaration', function (string $kind): void {
    $f = manualPaymentFixture();
    $file = match ($kind) {
        'size' => UploadedFile::fake()->create('large.pdf', 8193, 'application/pdf'),
        'image' => UploadedFile::fake()->createWithContent('bad.png', 'not an image'),
        'truncated' => UploadedFile::fake()->createWithContent('truncated.png', substr(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='), 0, 24)),
        'svg' => UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        default => UploadedFile::fake()->createWithContent('broken.pdf', '%PDF-1.4 broken'),
    };
    expect(fn () => app(SaasPaymentService::class)->declareExternalPayment($f['owner'], $f['invoice'], manualExternalPayload(), $file))->toThrow(ValidationException::class);
    expect(SaasPayment::count())->toBe(0);
})->with(['size', 'image', 'truncated', 'svg', 'pdf']);

test('reconciliation encrypts the real account and never verifies money or activates rights', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->declareExternalPayment($f['owner'], $f['invoice'], manualExternalPayload(),
        UploadedFile::fake()->createWithContent('preuve.pdf', MediaFactory::PDF_CONTENT));
    $payment = app(SaasPaymentService::class)->reconcilePayment($f['actor'], $payment, manualVerification($payment));
    expect($payment->getAttribute('transfer_status'))->toBe(TransferStatusEnum::DECLARED)
        ->and($payment->getAttribute('financial_account_key'))->not->toBe('TESTCCP001')
        ->and($payment->getRawOriginal('encrypted_transfer_details'))->not->toContain('TESTCCP001')
        ->and($f['subscription']->refresh()->status)->toBe(StatusEnum::SCHEDULED);
    $audit = Activity::forSubject($payment)->where('event', 'saas_payment_reconciled')->sole();
    expect(json_encode($audit->properties))->not->toContain('TESTCCP001');
});

test('the same physical CCP deposit cannot be verified again through BaridiMob', function (): void {
    $a = manualPaymentFixture();
    $b = manualPaymentFixture();
    $first = app(SaasPaymentService::class)->declareExternalPayment($a['owner'], $a['invoice'], [...manualExternalPayload(), 'method' => 2],
        UploadedFile::fake()->createWithContent('a.pdf', MediaFactory::PDF_CONTENT));
    $second = app(SaasPaymentService::class)->declareExternalPayment($b['owner'], $b['invoice'], [...manualExternalPayload(), 'method' => 3],
        UploadedFile::fake()->createWithContent('b.pdf', MediaFactory::PDF_CONTENT));
    app(SaasPaymentService::class)->verifyPayment($a['actor'], $first, manualVerification($first));
    expect(fn () => app(SaasPaymentService::class)->verifyPayment($b['actor'], $second, manualVerification($second)))->toThrow(ValidationException::class);
    expect(SaasPayment::where('transfer_status', TransferStatusEnum::VERIFIED)->count())->toBe(1)
        ->and($b['subscription']->refresh()->status)->toBe(StatusEnum::SCHEDULED);
});

test('T21-12 two real processes cannot settle the same obligation twice', function (string $scenario): void {
    $f = manualPaymentFixture();
    $other = User::factory()->create();
    $other->assignRole(Role::findByName('root', 'central'));
    $cash = [...manualExternalPayload(), 'received' => true];
    $payments = [];
    if ($scenario !== 'cash') {
        $payments[] = app(SaasPaymentService::class)->declareExternalPayment($f['owner'], $f['invoice'], manualExternalPayload(),
            UploadedFile::fake()->createWithContent('a.pdf', MediaFactory::PDF_CONTENT));
        $payments[] = $scenario === 'same' ? $payments[0] : app(SaasPaymentService::class)->declareExternalPayment($f['owner'], $f['invoice'],
            [...manualExternalPayload(), 'reference' => 'OTHER'], UploadedFile::fake()->createWithContent('b.pdf', MediaFactory::PDF_CONTENT));
    }
    $database = DB::connection()->getDatabaseName();
    expect($database)->toStartWith('aydra_test_');
    $c = config('database.connections.mysql');
    $environment = ['APP_ENV' => 'testing', 'DB_URL' => false, 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database,
        'DB_HOST' => $c['host'], 'DB_PORT' => (string) $c['port'], 'DB_USERNAME' => $c['username'], 'DB_PASSWORD' => $c['password'],
        'CACHE_STORE' => 'array', 'PAYMENT_STORAGE' => Storage::disk('local')->path(''), 'PAYMENT_INVOICE' => $f['invoice']->uuid];
    $script = <<<'PHP'
    require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $c=config('database.connections.mysql');
    if(!in_array($c['host'],['127.0.0.1','localhost','::1'],true)||!str_starts_with($c['database'],'aydra_test_')||!empty($c['url'])){throw new LogicException('Isolated local database required.');}
    config(['filesystems.disks.local'=>['driver'=>'local','root'=>getenv('PAYMENT_STORAGE'),'throw'=>true]]);
    $actor=App\Models\User::where('uuid',getenv('PAYMENT_ACTOR'))->firstOrFail();
    $data=json_decode(getenv('PAYMENT_DATA'),true,flags:JSON_THROW_ON_ERROR);
    $service=app(App\Services\Central\Billing\SaasPaymentService::class);
    if(getenv('PAYMENT_SCENARIO')==='cash'){
        $invoice=App\Models\Central\Billing\SaasInvoice::where('uuid',getenv('PAYMENT_INVOICE'))->firstOrFail();
        $result=$service->recordCashPayment($actor,$invoice,$data);
    }else{
        $payment=App\Models\Central\Billing\SaasPayment::where('uuid',getenv('PAYMENT_SUBJECT'))->firstOrFail();
        $result=$service->verifyPayment($actor,$payment,$data);
    }
    echo json_encode(['uuid'=>$result->uuid,'status'=>$result->transfer_status->value]);
    PHP;
    DB::commit();
    $processes = [];
    try {
        foreach ([$f['actor'], $other] as $index => $actor) {
            $process = new Process([PHP_BINARY, '-r', $script], base_path(), [...$environment, 'PAYMENT_ACTOR' => $actor->uuid,
                'PAYMENT_SCENARIO' => $scenario, 'PAYMENT_SUBJECT' => ($payments[$index] ?? null)?->uuid ?? '',
                'PAYMENT_DATA' => json_encode($scenario === 'cash' ? $cash : [...manualVerification($payments[$index]),
                    'reference' => $scenario === 'different' ? 'DISTINCT-RECEPTION-'.$index : 'TEST-REFERENCE-001'], JSON_THROW_ON_ERROR)]);
            $process->setTimeout(90)->start();
            $processes[] = $process;
        }
        foreach ($processes as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput());
        }
        expect(SaasPayment::where('transfer_status', TransferStatusEnum::VERIFIED)->count())->toBe(1)
            ->and(SaasPayment::count())->toBe($scenario === 'different' ? 2 : 1)
            ->and(SaasPayment::where('transfer_status', TransferStatusEnum::UNCERTAIN)->count())->toBe($scenario === 'different' ? 1 : 0)
            ->and(Activity::where('event', SaasPaymentService::VERIFIED)->count())->toBe(1)
            ->and($f['due']->refresh()->installment_status)->toBe(InstallmentStatusEnum::PAID)
            ->and(Media::where('collection_name', PaymentDocumentService::RECEIPT)->count())->toBe(1);
    } finally {
        foreach ($processes as $process) {
            $process->stop();
        }
        DB::beginTransaction();
    }
})->with(['cash', 'same', 'different']);

test('cash storage failure retains the real reception and resumes on the same operation', function (): void {
    $f = manualPaymentFixture();
    $disk = Storage::disk('local');
    $unavailable = Mockery::mock(FilesystemAdapter::class);
    $unavailable->shouldReceive('put')->once()->andReturnFalse();
    $unavailable->shouldReceive('delete')->once()->andReturnTrue();
    Storage::set('local', $unavailable);
    try {
        expect(fn () => app(SaasPaymentService::class)->recordCashPayment($f['actor'], $f['invoice'], [...manualExternalPayload(), 'received' => true]))
            ->toThrow(ValidationException::class);
    } finally {
        Storage::set('local', $disk);
    }
    $payment = SaasPayment::sole();
    expect($payment->getAttribute('transfer_status'))->toBe(TransferStatusEnum::DECLARED)
        ->and($payment->getAttribute('encrypted_transfer_details')['cash_received'])->toBeTrue()
        ->and($f['due']->refresh()->installment_status)->toBe(InstallmentStatusEnum::PENDING);
    $id = $payment->getKey();
    $result = app(SaasPaymentService::class)->resumeCashPayment($f['actor'], $payment, $payment->managementState());
    expect($result->getKey())->toBe($id)->and(SaasPayment::count())->toBe(1)
        ->and($result->getAttribute('transfer_status'))->toBe(TransferStatusEnum::VERIFIED);
});

test('a receipt failure preserves the confirmed payment and download safely generates its missing receipt', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->declareExternalPayment($f['owner'], $f['invoice'], manualExternalPayload(),
        UploadedFile::fake()->createWithContent('preuve.pdf', MediaFactory::PDF_CONTENT));
    $disk = Storage::disk('local');
    $unavailable = Mockery::mock($disk)->makePartial();
    $unavailable->shouldReceive('put')->once()->andReturnFalse();
    $unavailable->shouldReceive('delete')->once()->andReturnTrue();
    Storage::set('local', $unavailable);
    try {
        expect(fn () => app(SaasPaymentService::class)->verifyPayment($f['actor'], $payment, manualVerification($payment)))
            ->toThrow(ValidationException::class);
    } finally {
        Storage::set('local', $disk);
    }
    expect($payment->refresh()->getAttribute('transfer_status'))->toBe(TransferStatusEnum::VERIFIED)
        ->and($f['due']->refresh()->installment_status)->toBe(InstallmentStatusEnum::PAID)
        ->and($f['subscription']->refresh()->status)->toBe(StatusEnum::ACTIVE);
    $receipt = app(PaymentDocumentService::class)->generatePaymentReceipt($f['owner'], $payment);
    expect(Storage::disk('local')->get($receipt->storage_key))->toStartWith('%PDF-')
        ->and($payment->documents()->where('collection_name', PaymentDocumentService::RECEIPT)->count())->toBe(1);
});

test('verified receipt and payment metadata cannot be overwritten or deleted in SQL', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->recordCashPayment($f['actor'], $f['invoice'], [...manualExternalPayload(), 'received' => true]);
    $receipt = $payment->documents()->where('collection_name', PaymentDocumentService::RECEIPT)->sole();
    expect(fn () => DB::table('media')->where('id', $receipt->id)->update(['original_name' => 'Changed.pdf']))->toThrow(QueryException::class);
    expect(fn () => DB::table('media')->where('id', $receipt->id)->delete())->toThrow(QueryException::class);
    expect(fn () => DB::table('saas_transfers')->where('id', $payment->getKey())->update(['amount' => '1.00']))->toThrow(QueryException::class);
    expect(fn () => app(SaasPaymentService::class)->rejectPayment($f['actor'], $payment, $payment->managementState(), 'Mistake'))->toThrow(AuthorizationException::class);
});

test('an interruption after saving the converted PDF reuses the original image and PDF without replacing evidence', function (): void {
    $f = manualPaymentFixture();
    $file = UploadedFile::fake()->image('photo.png', 32, 32);
    $dispatcher = Media::getEventDispatcher();
    $event = 'eloquent.created: '.Media::class;
    $listeners = $dispatcher->getRawListeners()[$event] ?? [];
    Media::created(function (Media $media): void {
        if ($media->collection_name === PaymentDocumentService::PROOF) {
            throw new RuntimeException('Interrupted evidence attachment');
        }
    });
    try {
        expect(fn () => app(SaasPaymentService::class)->declareExternalPayment($f['owner'], $f['invoice'], manualExternalPayload(), $file))
            ->toThrow(RuntimeException::class, 'Interrupted evidence attachment');
    } finally {
        $dispatcher->forget($event);
        foreach ($listeners as $listener) {
            $dispatcher->listen($event, $listener);
        }
    }
    $payment = SaasPayment::sole();
    $proof = $payment->documents()->where('collection_name', PaymentDocumentService::PROOF)->sole();
    $originalHash = $proof->file_hash;
    expect($payment->getAttribute('proof_media_id'))->toBeNull();
    $this->travel(2)->seconds();
    $payment = app(SaasPaymentService::class)->resumeExternalProof($f['owner'], $payment, $payment->managementState(), $file);
    expect($payment->getAttribute('proof_media_id'))->toBe($proof->id)
        ->and($payment->documents()->where('collection_name', PaymentDocumentService::SOURCE)->count())->toBe(1)
        ->and($payment->documents()->where('collection_name', PaymentDocumentService::PROOF)->count())->toBe(1)
        ->and($proof->refresh()->file_hash)->toBe($originalHash);
    app(SaasPaymentService::class)->verifyPayment($f['actor'], $payment, manualVerification($payment));
    expect(SaasPayment::count())->toBe(1)->and($f['subscription']->refresh()->status)->toBe(StatusEnum::ACTIVE);
});

test('taxes are already included in the issued total and payment never adds them a second time', function (): void {
    $f = manualPaymentFixture(rates: [['code' => 'TEST', 'rate' => '19.00']]);
    expect($f['invoice']->total_amount)->toBe('3000.00')->and($f['invoice']->tax_amount)->not->toBe('0.00');
    $payment = app(SaasPaymentService::class)->recordCashPayment($f['actor'], $f['invoice'], [...manualExternalPayload(), 'received' => true]);
    expect($payment->getAttribute('amount'))->toBe('3000.00')
        ->and($f['due']->refresh()->installment_status)->toBe(InstallmentStatusEnum::PAID);
});

test('a full renewal is paid once and takes over only at the end of the current paid period', function (): void {
    $f = manualPaymentFixture(purchase: true);
    $service = app(SaasPaymentService::class);
    $service->recordCashPayment($f['actor'], $f['invoice'], [...manualExternalPayload(), 'received' => true]);
    $next = app(SubscriptionPurchaseService::class)->choose($f['owner'], ['plan_uuid' => $f['subscription']->plan->uuid,
        'period' => 1, 'expected_amount' => '3000.00', 'operation_id' => (string) Str::uuid()]);
    $due = SubscriptionInstallment::where('subscription_id', $next->id)->sole();
    $rule = $f['invoice']->billingRule;
    $draft = app(SaasInvoiceService::class)->prepareInstallment($f['actor'], $next, $due, $rule->uuid, $due->managementState());
    $invoice = app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $draft, $draft->managementState());
    $data = [...manualExternalPayload(), 'received' => true];
    $service->recordCashPayment($f['actor'], $invoice, $data);
    $service->recordCashPayment($f['actor'], $invoice, $data);
    expect($next->refresh()->status)->toBe(StatusEnum::SCHEDULED)
        ->and($f['subscription']->refresh()->status)->toBe(StatusEnum::ACTIVE)
        ->and($next->period_starts_at->equalTo($f['subscription']->period_ends_at))->toBeTrue();
    $end = $next->period_ends_at;
    $this->travelTo($next->period_starts_at->addSecond());
    $defaults = app(DefaultSubscription::class);
    expect($defaults->ensure($f['owner'])->id)->toBe($next->id)
        ->and($defaults->ensure($f['owner'])->period_ends_at->equalTo($end))->toBeTrue()
        ->and(SaasPayment::count())->toBe(2)
        ->and(Activity::forSubject($next)->where('event', 'subscription_scheduled_activated')->count())->toBe(1);
});

test('an expired period keeps a reported reception as an anomaly without granting rights', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->declareExternalPayment($f['owner'], $f['invoice'], manualExternalPayload(),
        UploadedFile::fake()->createWithContent('preuve.pdf', MediaFactory::PDF_CONTENT));
    $this->travel(2)->months();
    $payment = app(SaasPaymentService::class)->verifyPayment($f['actor'], $payment, manualVerification($payment));
    expect($payment->getAttribute('transfer_status'))->toBe(TransferStatusEnum::UNCERTAIN)
        ->and($f['due']->refresh()->installment_status)->toBe(InstallmentStatusEnum::PENDING)
        ->and($f['subscription']->refresh()->status)->toBe(StatusEnum::SCHEDULED);
});

test('owner purchase validates current prices and preserves free rights without artificial payment', function (): void {
    $f = manualPaymentFixture();
    $owner = User::factory()->create();
    $owner->forceFill(['legal_verification_status' => VerificationStatusEnum::VERIFIED, 'legal_verified_at' => now(),
        'legal_verified_by_id' => $f['actor']->id, 'legal_profile_version' => 1])->save();
    $this->seed(FreePlanSeeder::class);
    $free = app(DefaultSubscription::class)->ensure($owner);
    $plan = Plan::factory()->create(['is_active' => true, 'monthly_price' => '3000.00', 'annual_price' => '36000.00']);
    $data = ['plan_uuid' => $plan->uuid, 'period' => 2, 'operation_id' => (string) Str::uuid(), 'expected_amount' => '36000.00',
        'payment_choice' => PaymentChoiceEnum::CASH->value];
    $this->actingAs($owner, 'central')->get(route('owner.subscriptions.index'))->assertOk()->assertSee($plan->name);
    $this->post(route('owner.subscriptions.store'), [...$data, 'expected_amount' => '1.00'])->assertSessionHasErrors('plan_uuid');
    $this->post(route('owner.subscriptions.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
    $this->post(route('owner.subscriptions.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
    $paid = Subscription::where('user_id', $owner->id)->where('plan_id', $plan->id)->sole();
    expect($paid->agreed_amount)->toBe('36000.00')->and($paid->status)->toBe(StatusEnum::SCHEDULED)
        ->and($paid->period_ends_at->equalTo($paid->period_starts_at->addYearNoOverflow()))->toBeTrue()
        ->and($free->refresh()->status)->toBe(StatusEnum::ACTIVE)
        ->and(SubscriptionInstallment::where('subscription_id', $free->id)->count())->toBe(0);
    $this->get(route('owner.subscriptions.show', $paid))->assertOk();
});

test('an expired unpaid purchase does not prevent a new period and administrative keys stay bound to their owner', function (): void {
    $f = manualPaymentFixture(purchase: true);
    $this->travel(2)->months();
    $purchase = app(SubscriptionPurchaseService::class);
    $data = ['plan_uuid' => $f['subscription']->plan->uuid, 'period' => 1,
        'operation_id' => (string) Str::uuid(), 'expected_amount' => '3000.00'];
    $new = $purchase->choose($f['owner'], $data);
    expect($new->id)->not->toBe($f['subscription']->id)
        ->and($f['subscription']->refresh()->status)->toBe(StatusEnum::EXPIRED)
        ->and($new->status)->toBe(StatusEnum::SCHEDULED)
        ->and($f['due']->refresh()->installment_status)->toBe(InstallmentStatusEnum::PENDING);
    $a = User::factory()->create();
    $b = User::factory()->create();
    $purchase->chooseFor($f['actor'], $a, $data);
    expect(fn () => $purchase->chooseFor($f['actor'], $b, $data))->toThrow(ValidationException::class);
    expect(Subscription::where('user_id', $b->id)->count())->toBe(0);
});

test('T21-21 a SQL failure rolls back funds confirmation the period rights and audit together', function (): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->declareExternalPayment($f['owner'], $f['invoice'], manualExternalPayload(),
        UploadedFile::fake()->createWithContent('preuve.pdf', MediaFactory::PDF_CONTENT));
    $dispatcher = Activity::getEventDispatcher();
    $event = 'eloquent.created: '.Activity::class;
    $listeners = $dispatcher->getRawListeners()[$event] ?? [];
    Activity::created(function (Activity $current): void {
        if ($current->getAttribute('event') === SaasPaymentService::VERIFIED) {
            DB::statement("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Controlled payment failure'");
        }
    });
    try {
        expect(fn () => app(SaasPaymentService::class)->verifyPayment($f['actor'], $payment, manualVerification($payment)))
            ->toThrow(QueryException::class);
    } finally {
        $dispatcher->forget($event);
        foreach ($listeners as $listener) {
            $dispatcher->listen($event, $listener);
        }
    }
    expect($payment->refresh()->getAttribute('transfer_status'))->toBe(TransferStatusEnum::DECLARED)
        ->and($f['due']->refresh()->installment_status)->toBe(InstallmentStatusEnum::PENDING)
        ->and($f['subscription']->refresh()->status)->toBe(StatusEnum::SCHEDULED)
        ->and(Activity::forSubject($payment)->where('event', SaasPaymentService::VERIFIED)->count())->toBe(0)
        ->and($payment->documents()->where('collection_name', PaymentDocumentService::RECEIPT)->count())->toBe(0);
});

test('T21-23 payment services refuse a real initialized tenant and preserve its canary', function (): void {
    $f = manualPaymentFixture();
    Event::fake([TenantCreated::class]);
    $tenant = Tenant::factory()->create(['slug' => 'payment-'.Str::lower(Str::random(8))]);
    $database = $tenant->database()->getName();
    expect($database)->toStartWith(config('tenancy.database.prefix'));
    DB::commit();
    try {
        CreateDatabase::dispatchSync($tenant);
        $tenant->run(function () use ($f): void {
            DB::statement('CREATE TABLE payment_canary (id INT PRIMARY KEY, value INT NOT NULL)');
            DB::table('payment_canary')->insert(['id' => 1, 'value' => 42]);
            expect(fn () => app(SaasPaymentService::class)->recordCashPayment($f['actor'], $f['invoice'], [...manualExternalPayload(), 'received' => true]))
                ->toThrow(HttpException::class);
            expect(fn () => app(SubscriptionPurchaseService::class)->choose($f['owner'], []))
                ->toThrow(HttpException::class);
            expect(DB::table('payment_canary')->sole()->value)->toBe(42)
                ->and(DB::getSchemaBuilder()->hasTable('saas_transfers'))->toBeFalse();
        });
        expect(SaasPayment::count())->toBe(0);
    } finally {
        tenancy()->end();
        DB::purge('tenant');
        TestCase::dropIsolatedMysqlDatabase($database);
        DB::beginTransaction();
    }
});

test('public offer presentation is cached after commit and invalidated without caching payment decisions', function (): void {
    $plan = Plan::factory()->create(['is_active' => true, 'name' => 'Before commit']);
    DB::commit();
    try {
        $catalog = app(PublicPlanCatalog::class);
        $catalog->invalidate();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $first = $catalog->available();
        expect(count(DB::getQueryLog()))->toBe(2);
        DB::flushQueryLog();
        $catalog->available();
        $catalog->available();
        expect(DB::getQueryLog())->toBe([])->and($first[0]['name'])->toBe('Before commit');
        DB::disableQueryLog();
        $plan->forceFill(['name' => 'After commit'])->save();
        expect($catalog->available()[0]['name'])->toBe('After commit');
        $plan->forceFill(['is_active' => false])->save();
        expect($catalog->available())->toBe([]);
    } finally {
        DB::disableQueryLog();
        DB::beginTransaction();
    }
});

test('payment permissions authorize separate administrative actions and never grant owner access to staff', function (string $permission, string $ability): void {
    $f = manualPaymentFixture();
    $payment = app(SaasPaymentService::class)->declareExternalPayment($f['owner'], $f['invoice'], manualExternalPayload(),
        UploadedFile::fake()->createWithContent('preuve.pdf', MediaFactory::PDF_CONTENT));
    $staff = User::factory()->create();
    $staff->assignRole(Role::createWithPermissions('payment-'.Str::random(8), ['saas.payments.'.$permission => 7]));
    foreach (['viewAny' => SaasPayment::class, 'verify' => $payment, 'reject' => $payment, 'reconcile' => $payment] as $candidate => $target) {
        expect(Gate::forUser($staff)->allows($candidate, $target))->toBe($candidate === $ability);
    }
    $this->actingAs($staff, 'central')->get(route('owner.payments.index'))->assertForbidden();
})->with([['view', 'viewAny'], ['validate', 'verify'], ['reject', 'reject'], ['reconcile', 'reconcile']]);

test('browser records an isolated payment and the owner reads the verified private receipt', function (bool $cash): void {
    $f = manualPaymentFixture(purchase: true);
    $runtime = storage_path('framework/testing/module21-browser-'.Str::uuid());
    foreach (['framework/views', 'framework/cache/data', 'framework/sessions', 'logs', 'app/private'] as $directory) {
        File::ensureDirectoryExists($runtime.'/'.$directory);
    }
    File::copyDirectory(Storage::disk('local')->path(''), $runtime.'/app/private');
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
        'DB_CONNECTION' => 'mysql', 'DB_URL' => false, 'DB_DATABASE' => DB::connection()->getDatabaseName(),
        'DB_HOST' => $c['host'], 'DB_PORT' => (string) $c['port'], 'DB_USERNAME' => $c['username'], 'DB_PASSWORD' => $c['password'],
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'file', 'SESSION_SECURE_COOKIE' => 'false', 'SESSION_DOMAIN' => '(null)',
        'SESSION_COOKIE' => 'module21_'.Str::random(12), 'LARAVEL_STORAGE_PATH' => $runtime, 'MAIL_MAILER' => 'log',
        'APP_CONFIG_CACHE' => $runtime.'/config.php', 'APP_ROUTES_CACHE' => $runtime.'/routes.php'];
    DB::commit();
    $server = new Process([PHP_BINARY, 'artisan', 'serve', '--host=127.0.0.1', '--port='.$port, '--no-reload', '--no-interaction'], base_path(), $environment);
    $server->setTimeout(null);
    try {
        $server->start();
        File::put($runtime.'/proof.pdf', MediaFactory::PDF_CONTENT);
        File::put($runtime.'/ready.json', json_encode(['url' => $base, 'invoice' => $f['invoice']->uuid,
            'cash' => $cash, 'proof' => $runtime.'/proof.pdf', 'admin' => $f['actor']->email, 'owner' => $f['owner']->email], JSON_THROW_ON_ERROR));
        $deadline = microtime(true) + 900;
        while (! is_file($runtime.'/complete.json') && microtime(true) < $deadline && $server->isRunning()) {
            usleep(200000);
        }
        expect(is_file($runtime.'/complete.json'))->toBeTrue($server->getErrorOutput());
        expect(SaasPayment::sole()->getAttribute('transfer_status'))->toBe(TransferStatusEnum::VERIFIED)
            ->and($f['subscription']->refresh()->status)->toBe(StatusEnum::ACTIVE)
            ->and($f['due']->refresh()->installment_status)->toBe(InstallmentStatusEnum::PAID)
            ->and(Media::where('collection_name', PaymentDocumentService::RECEIPT)->count())->toBe(1);
    } finally {
        $server->stop();
        DB::beginTransaction();
        File::deleteDirectory($runtime);
    }
})->with(['cash' => [true], 'external' => [false]])
    ->skip(fn (): bool => getenv('AYDRA_MODULE21_BROWSER') !== '1', 'Requires an interactive browser pilot on isolated data.');
