<?php

use App\Enums\Central\Billing\DocumentStatusEnum;
use App\Enums\Central\Billing\DocumentTypeEnum;
use App\Enums\Central\Billing\TransferStatusEnum;
use App\Enums\Central\Billing\TriggerEventEnum;
use App\Enums\Central\Subscriptions\StatusEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Billing\BillingSequence;
use App\Models\Central\Billing\SaasCreditNote;
use App\Models\Central\Billing\SaasCreditNoteLine;
use App\Models\Central\Billing\SaasInvoiceLine;
use App\Models\Central\Billing\SaasPayment;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Roles\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Central\Billing\BillingRuleService;
use App\Services\Central\Billing\InvoiceCalculator;
use App\Services\Central\Billing\InvoicePaymentService;
use App\Services\Central\Billing\SaasCreditNoteService;
use App\Services\Central\Billing\SaasInvoiceIssuanceService;
use App\Services\Central\Billing\SaasInvoicePdfService;
use App\Services\Central\Billing\SaasNumberingService;
use App\Services\Central\Billing\SaasPaymentService;
use Database\Seeders\Central\CreditNotePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

require_once dirname(__DIR__, 4).'/Support/ManualBillingFixtures.php';

/** @return array<string, mixed> */
function creditNoteFixture(bool $issued = true, array $rates = []): array
{
    $fixture = manualPaymentFixture(rates: $rates, issued: $issued);
    $rules = app(BillingRuleService::class);
    $rule = $rules->create($fixture['actor'], ['code' => 'credit.test.'.Str::lower(Str::random(8)),
        'trigger_event' => TriggerEventEnum::INVOICE_CORRECTION->value, 'parameters' => ['document_type' => DocumentTypeEnum::CREDIT_NOTE->value, 'payment_due_days' => 0]], (string) Str::uuid());
    $rules->validateRule($fixture['actor'], $rule, $rule->managementState(), 'TEST-CREDIT');
    $rule->refresh();
    $rules->activate($fixture['actor'], $rule, $rule->managementState(), now()->subDay()->toDateTimeString(), null);

    return [...$fixture, 'creditRule' => $rule];
}

/** @return array<string, mixed> */
function creditNoteInput(array $fixture, string $total = '500.00', string $quantity = '0.10'): array
{
    return ['billing_rule_uuid' => $fixture['creditRule']->uuid, 'operation_id' => (string) Str::uuid(),
        'expected_state' => $fixture['invoice']->managementState(), 'reason' => 'Correction fictive autorisée',
        'lines' => [['original_line_uuid' => $fixture['invoice']->lines()->sole()->uuid, 'quantity' => $quantity, 'total_amount' => $total, 'reason' => 'Réduction de test']]];
}

test('T20-01 a credit draft reserves an exact correction without changing its issued original', function (): void {
    $f = creditNoteFixture();
    $before = $f['invoice']->getAttributes();
    $credit = app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f));

    expect($credit)->toBeInstanceOf(SaasCreditNote::class)
        ->and($credit->status)->toBe(DocumentStatusEnum::DRAFT)
        ->and($credit->document_type)->toBe(DocumentTypeEnum::CREDIT_NOTE)
        ->and($credit->total_amount)->toBe('500.00')
        ->and($credit->getAttribute('original_invoice_id'))->toBe($f['invoice']->id)
        ->and($credit->lines()->sole()->getAttribute('original_invoice_line_id'))->toBe($f['invoice']->lines()->sole()->id)
        ->and($f['invoice']->refresh()->getAttributes())->toBe($before);
});

test('T20-02 a draft invoice cannot be corrected', function (): void {
    $f = creditNoteFixture(issued: false);
    expect(fn () => app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f)))->toThrow(ValidationException::class);
    expect(SaasCreditNote::count())->toBe(0);
});

test('T20-05 T20-06 T20-08 reserved corrections consume each original dimension', function (string $quantity, string $amount): void {
    $f = creditNoteFixture();
    $service = app(SaasCreditNoteService::class);
    $service->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f, '1000.00', '0.50'));
    expect(fn () => $service->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f, $amount, $quantity)))->toThrow(ValidationException::class);
    expect(SaasCreditNote::count())->toBe(1);
})->with([['0.60', '100.00'], ['0.10', '2500.00']]);

test('T20-09 cancelling an unissued correction releases its reservation without deleting its history', function (): void {
    $f = creditNoteFixture();
    $service = app(SaasCreditNoteService::class);
    $credit = $service->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f, '2500.00', '0.80'));
    $service->cancelDraft($f['actor'], $credit, $credit->managementState(), 'Brouillon remplacé');
    $next = $service->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f, '3000.00', '1.00'));
    expect($credit->refresh()->status)->toBe(DocumentStatusEnum::CANCELLED)
        ->and($credit->lines()->count())->toBe(1)->and($next->total_amount)->toBe('3000.00');
});

test('T20-12 T20-13 T20-14 a credit uses its own number and PDF without rewriting the invoice', function (): void {
    $f = creditNoteFixture();
    $invoiceState = $f['invoice']->getAttributes();
    $originalHash = $f['invoice']->media->file_hash;
    $credit = app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f));
    $service = app(SaasInvoiceIssuanceService::class);
    $credit = $service->issue($f['actor'], $credit, $credit->managementState());
    $replay = $service->issue($f['actor'], $credit, $credit->managementState());
    expect($credit->status)->toBe(DocumentStatusEnum::ISSUED)->and($credit->number)->toStartWith('AVOIR-')
        ->and($replay->id)->toBe($credit->id)->and($credit->deliveries()->count())->toBe(1)
        ->and(BillingSequence::where('document_type', DocumentTypeEnum::INVOICE)->sole()->next_number)->toBe(2)
        ->and(BillingSequence::where('document_type', DocumentTypeEnum::CREDIT_NOTE)->sole()->next_number)->toBe(2)
        ->and(Storage::disk('local')->get($credit->media->storage_key))->toStartWith('%PDF-')
        ->and($f['invoice']->refresh()->getAttributes())->toBe($invoiceState)
        ->and($f['invoice']->media->file_hash)->toBe($originalHash);
});

test('T20-15 an issued correction reduces debt and leaves the real funds untouched', function (): void {
    $f = creditNoteFixture();
    app(SaasPaymentService::class)->recordCashPayment($f['actor'], $f['invoice'], [...manualExternalPayload(), 'received' => true]);
    $credit = app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f));
    app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $credit, $credit->managementState());
    $balance = app(InvoicePaymentService::class)->balance($f['owner'], $f['invoice']);
    expect($balance['net_due'])->toBe('2500.00')->and($balance['payments'])->toBe('3000.00')
        ->and($balance['refunds'])->toBe('0.00')->and($balance['surplus'])->toBe('500.00')
        ->and(DB::table('saas_transfers')->where('record_type', 2)->count())->toBe(0);
});

test('credit HTTP workflow supports preparation issuance private download and the owners issued-only view', function (): void {
    $f = creditNoteFixture();
    $this->actingAs($f['actor'], 'central')->get(route('central.credit-notes.create', $f['invoice']))->assertOk()->assertSee('Disponible');
    $this->post(route('central.credit-notes.store', $f['invoice']), creditNoteInput($f))->assertSessionHasNoErrors()->assertRedirect();
    $credit = SaasCreditNote::sole();
    $this->get(route('central.credit-notes.show', $credit))->assertOk()->assertSee('Valider et réserver le numéro');
    $this->actingAs($f['owner'], 'central')->get(route('owner.credit-notes.show', $credit))->assertForbidden();
    $this->get(route('owner.credit-notes.index'))->assertOk()->assertDontSee('Brouillon');
    $this->actingAs($f['actor'], 'central')->post(route('central.credit-notes.prepare', $credit), ['expected_state' => $credit->managementState()])->assertSessionHasNoErrors();
    $credit->refresh();
    $this->post(route('central.credit-notes.issue', $credit), ['expected_state' => $credit->managementState()])->assertSessionHasNoErrors();
    $credit->refresh();
    $this->actingAs($f['owner'], 'central')->get(route('owner.credit-notes.show', $credit))->assertOk()->assertSee($credit->number)->assertDontSee('Annuler le brouillon');
    $this->get(URL::temporarySignedRoute('owner.credit-notes.download', now()->addMinutes(5), ['credit' => $credit]))->assertOk();
    $this->get(route('central.credit-notes.index'))->assertForbidden();
    $this->actingAs(User::factory()->create(), 'central')->get(route('owner.credit-notes.show', $credit))->assertForbidden();
    $this->get(URL::temporarySignedRoute('owner.credit-notes.download', now()->addMinutes(5), ['credit' => $credit]))->assertForbidden();
});

test('T20-16 every administrative credit route denies staff without credit permissions', function (): void {
    $f = creditNoteFixture();
    $credit = app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f));
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('no-credit-'.Str::random(8), ['saas.users.view' => 7]));
    $this->actingAs($actor, 'central');
    foreach (['index' => null, 'show' => $credit, 'edit' => $credit, 'create' => $f['invoice']] as $action => $target) {
        $this->get(route('central.credit-notes.'.$action, $target))->assertForbidden();
    }
    $this->post(route('central.credit-notes.store', $f['invoice']), [])->assertForbidden();
    $this->put(route('central.credit-notes.update', $credit), [])->assertForbidden();
    foreach (['prepare', 'issue', 'cancel'] as $action) {
        $this->post(route('central.credit-notes.'.$action, $credit), [])->assertForbidden();
    }
    $this->get(URL::temporarySignedRoute('central.credit-notes.download', now()->addMinutes(5), ['credit' => $credit]))->assertForbidden();
});

test('a full payment after an issued credit settles the corrected debt without editing the invoice or accepting installments', function (): void {
    $f = creditNoteFixture();
    $credit = app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f));
    app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $credit, $credit->managementState());
    $this->actingAs($f['owner'], 'central')->get(route('owner.payments.create', $f['invoice']))->assertOk()->assertSee('2500.00');
    $payment = app(SaasPaymentService::class)->recordCashPayment($f['actor'], $f['invoice'], [...manualExternalPayload('2500.00'), 'received' => true]);
    expect($payment->getAttribute('amount'))->toBe('2500.00')
        ->and($payment->getAttribute('transfer_status'))->toBe(TransferStatusEnum::VERIFIED)
        ->and($f['invoice']->refresh()->total_amount)->toBe('3000.00')
        ->and(app(InvoicePaymentService::class)->balance($f['owner'], $f['invoice'])['remaining'])->toBe('0.00');
    $this->get(route('owner.invoices.show', $f['invoice']))->assertOk()->assertSee('2500.00')->assertSee('Paiement intégral confirmé');
});

test('T20-07 two compatible issued credits accumulate without modifying original quantities or granting subscription rights', function (): void {
    $f = creditNoteFixture();
    $service = app(SaasCreditNoteService::class);
    foreach ([['1000.00', '0.30'], ['1500.00', '0.50']] as [$amount, $quantity]) {
        $credit = $service->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f, $amount, $quantity));
        app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $credit, $credit->managementState());
    }
    $available = $service->availableLines($f['actor'], $f['invoice']);
    $line = array_values($available)[0];
    expect($line['issued']['total_amount'])->toBe('2500.00')->and($line['remaining']['total_amount'])->toBe('500.00')
        ->and($line['remaining']['quantity'])->toBe('0.20')
        ->and($f['subscription']->refresh()->status)->toBe(StatusEnum::SCHEDULED);
});

test('a correction operation is idempotent and rejects a different payload', function (): void {
    $f = creditNoteFixture();
    $input = creditNoteInput($f);
    $service = app(SaasCreditNoteService::class);
    $credit = $service->createCreditNoteDraft($f['actor'], $f['invoice'], $input);
    expect($service->createCreditNoteDraft($f['actor'], $f['invoice'], $input)->id)->toBe($credit->id);
    expect(fn () => $service->createCreditNoteDraft($f['actor'], $f['invoice'], [...$input, 'reason' => 'Autre correction']))->toThrow(ValidationException::class);
    expect(SaasCreditNote::count())->toBe(1)->and(Activity::forSubject($credit)->where('event', SaasCreditNoteService::CREATED)->count())->toBe(1);
});

test('editing a draft recalculates its own reservation and rejects obsolete quantities and excessive amounts atomically', function (): void {
    $f = creditNoteFixture();
    $service = app(SaasCreditNoteService::class);
    $input = creditNoteInput($f);
    $credit = $service->createCreditNoteDraft($f['actor'], $f['invoice'], $input);
    unset($input['operation_id']);
    $input['expected_state'] = $credit->managementState();
    $input['lines'][0]['quantity'] = '0.20';
    $credit = $service->updateDraft($f['actor'], $credit, $input);
    expect($credit->lines()->sole()->quantity)->toBe('0.20');
    expect(Activity::forSubject($credit)->where('event', SaasCreditNoteService::CREATED)->sole()->getProperty('lines')[0]['quantity'])->toBe('0.10')
        ->and(Activity::forSubject($credit)->where('event', SaasCreditNoteService::UPDATED)->sole()->getProperty('lines')[0]['quantity'])->toBe('0.20');
    expect(fn () => $service->updateDraft($f['actor'], $credit, $input))->toThrow(ValidationException::class);
    $before = $credit->lines()->sole()->getAttributes();
    $input['expected_state'] = $credit->managementState();
    $input['lines'][0]['total_amount'] = '3000.01';
    expect(fn () => $service->updateDraft($f['actor'], $credit, $input))->toThrow(ValidationException::class);
    expect($credit->refresh()->total_amount)->toBe('500.00')->and($credit->lines()->sole()->getAttributes())->toBe($before);
});

test('T20-03 T20-04 a foreign original line or an injected owner never produces a correction', function (): void {
    $f = creditNoteFixture();
    $foreign = creditNoteFixture();
    $input = creditNoteInput($f);
    $input['lines'][0]['original_line_uuid'] = $foreign['invoice']->lines()->sole()->uuid;
    expect(fn () => app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $f['invoice'], $input))->toThrow(ValidationException::class);
    $this->actingAs($f['actor'], 'central')->post(route('central.credit-notes.store', $f['invoice']), [...creditNoteInput($f), 'user_id' => $foreign['owner']->id])->assertSessionHasErrors('user_id');
    expect(SaasCreditNote::count())->toBe(0);
});

test('T20-11 original taxes are preserved and exact centimes are used for fractional corrections', function (): void {
    $f = creditNoteFixture(rates: [['code' => 'TVA', 'rate' => '19']]);
    $credit = app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f, '119.00', '0.10'));
    expect($credit->net_amount)->toBe('100.00')->and($credit->tax_amount)->toBe('19.00')
        ->and($credit->getAttribute('taxes'))->toBe([['code' => 'TVA', 'rate' => '19', 'amount' => '19.00']]);
    $credit = app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $credit, $credit->managementState());
    expect($credit->total_amount)->toBe('119.00')->and($credit->lines()->sole()->quantity)->toBe('0.10');
});

test('a numbered correction stays frozen and cancelled numbers are never reused', function (): void {
    $f = creditNoteFixture();
    $credits = app(SaasCreditNoteService::class);
    $credit = $credits->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f));
    $credit = app(SaasNumberingService::class)->reserveNextNumber($f['actor'], $credit, $credit->managementState(), $credits->assertIssuable(...));
    expect($credit->status)->toBe(DocumentStatusEnum::PREPARING);
    $this->actingAs($f['actor'], 'central')->get(route('central.credit-notes.edit', $credit))->assertForbidden();
    $credits->cancelDraft($f['actor'], $credit, $credit->managementState(), 'Numéro conservé, correction abandonnée');
    $next = $credits->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f));
    $next = app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $next, $next->managementState());
    expect($next->number)->not->toBe($credit->number)->and($credit->refresh()->number)->not->toBeNull()
        ->and(BillingSequence::where('document_type', DocumentTypeEnum::CREDIT_NOTE)->sole()->next_number)->toBe(3);
});

test('issued credit headers lines and media are protected by SQL and a tampered private PDF is refused', function (): void {
    $f = creditNoteFixture();
    $credit = app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f));
    $credit = app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $credit, $credit->managementState());
    expect(fn () => DB::table('saas_invoices')->where('id', $credit->id)->update(['total_amount' => '1.00']))->toThrow(QueryException::class);
    expect(fn () => DB::table('saas_invoice_lines')->where('document_id', $credit->id)->delete())->toThrow(QueryException::class);
    expect(fn () => DB::table('media')->where('id', $credit->media->id)->update(['storage_key' => 'other.pdf']))->toThrow(QueryException::class);
    expect(fn () => DB::table('saas_invoices')->where('id', $credit->id)->delete())->toThrow(QueryException::class);
    Storage::disk('local')->put($credit->media->storage_key, '%PDF-corrupted');
    expect(fn () => app(SaasInvoiceIssuanceService::class)->download($f['owner'], $credit))->toThrow(ValidationException::class);
    expect($credit->refresh()->status)->toBe(DocumentStatusEnum::ISSUED);
});

test('a SQL failure after finalization rolls back the credit media balance delivery and audit and can be retried', function (): void {
    $f = creditNoteFixture();
    $credit = app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f, '3000.00', '1.00'));
    $dispatcher = Activity::getEventDispatcher();
    $event = 'eloquent.created: '.Activity::class;
    $listeners = $dispatcher->getRawListeners()[$event] ?? [];
    Activity::created(function (Activity $activity): void {
        if ($activity->event === SaasInvoiceIssuanceService::CREDIT_ISSUED) {
            DB::statement("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Controlled credit failure'");
        }
    });
    try {
        expect(fn () => app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $credit, $credit->managementState()))->toThrow(ValidationException::class);
    } finally {
        $dispatcher->forget($event);
        foreach ($listeners as $listener) {
            $dispatcher->listen($event, $listener);
        }
    }
    $credit->refresh();
    $number = $credit->number;
    expect($credit->status)->toBe(DocumentStatusEnum::PREPARING)->and($credit->getAttribute('document_media_id'))->toBeNull()
        ->and($credit->deliveries()->count())->toBe(0)->and(app(InvoicePaymentService::class)->balance($f['owner'], $f['invoice'])['net_due'])->toBe('3000.00');
    $credit = app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $credit, $credit->managementState());
    expect($credit->number)->toBe($number)->and($credit->deliveries()->count())->toBe(1)
        ->and(Activity::forSubject($credit)->where('event', SaasInvoiceIssuanceService::CREDIT_ISSUED)->count())->toBe(1);
});

test('a credit keeps its fiscal snapshot but its pending transmission uses the owners current verified address', function (): void {
    $f = creditNoteFixture();
    $credit = app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f));
    $old = $credit->customer_identity_snapshot['email'];
    $f['owner']->forceFill(['email' => 'new-address@example.test', 'email_verified_at' => null])->save();
    expect(fn () => app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $credit, $credit->managementState()))->toThrow(ValidationException::class);
    $f['owner']->forceFill(['email_verified_at' => now()])->save();
    $credit->refresh();
    $credit = app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $credit, $credit->managementState());
    expect($credit->customer_identity_snapshot['email'])->toBe($old)
        ->and($credit->deliveries()->sole()->getAttribute('encrypted_recipient'))->toBe('new-address@example.test');
});

test('a fully credited unpaid invoice is not a payment and never activates a subscription', function (): void {
    $f = creditNoteFixture();
    $credit = app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f, '3000.00', '1.00'));
    app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $credit, $credit->managementState());
    expect(app(InvoicePaymentService::class)->balance($f['owner'], $f['invoice'])['surplus'])->toBe('0.00')
        ->and(SaasPayment::count())->toBe(0)->and($f['subscription']->refresh()->status)->toBe(StatusEnum::SCHEDULED);
    $this->actingAs($f['owner'], 'central')->get(route('owner.invoices.show', $f['invoice']))->assertOk()->assertSee('Dette intégralement corrigée par avoir');
});

test('T20-10 two real processes serialize reservations and duplicate creation or issuance', function (string $scenario): void {
    $f = creditNoteFixture();
    $other = User::factory()->create();
    $other->assignRole(Role::findByName('root', 'central'));
    $input = creditNoteInput($f, '2000.00', '0.60');
    $credit = $scenario === 'issue' ? app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $f['invoice'], $input) : null;
    $c = config('database.connections.mysql');
    $environment = ['APP_ENV' => 'testing', 'DB_URL' => false, 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => DB::connection()->getDatabaseName(),
        'DB_HOST' => $c['host'], 'DB_PORT' => (string) $c['port'], 'DB_USERNAME' => $c['username'], 'DB_PASSWORD' => $c['password'],
        'CACHE_STORE' => 'array', 'CREDIT_STORAGE' => Storage::disk('local')->path(''), 'CREDIT_INVOICE' => $f['invoice']->uuid,
        'CREDIT_SCENARIO' => $scenario, 'CREDIT_SUBJECT' => $credit?->uuid ?? ''];
    $script = <<<'PHP'
    require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $c=config('database.connections.mysql');
    if(!in_array($c['host'],['127.0.0.1','localhost','::1'],true)||!str_starts_with($c['database'],'aydra_test_')||!empty($c['url'])){throw new LogicException('Isolated local database required.');}
    config(['filesystems.disks.local'=>['driver'=>'local','root'=>getenv('CREDIT_STORAGE'),'throw'=>true]]);
    $actor=App\Models\User::where('uuid',getenv('CREDIT_ACTOR'))->firstOrFail();
    $data=json_decode(getenv('CREDIT_DATA'),true,flags:JSON_THROW_ON_ERROR);
    try {
        if(getenv('CREDIT_SCENARIO')==='issue'){
            $credit=App\Models\Central\Billing\SaasCreditNote::where('uuid',getenv('CREDIT_SUBJECT'))->firstOrFail();
            app(App\Services\Central\Billing\SaasInvoiceIssuanceService::class)->issue($actor,$credit,$credit->managementState());
        }else{
            $invoice=App\Models\Central\Billing\SaasInvoice::where('uuid',getenv('CREDIT_INVOICE'))->firstOrFail();
            app(App\Services\Central\Billing\SaasCreditNoteService::class)->createCreditNoteDraft($actor,$invoice,$data);
        }
        echo 'ok';
    }catch(Illuminate\Validation\ValidationException $e){echo 'conflict';}
    PHP;
    DB::commit();
    $processes = [];
    try {
        foreach ([$f['actor'], $other] as $index => $actor) {
            $data = $scenario === 'reservations' && $index === 1 ? [...$input, 'operation_id' => (string) Str::uuid()] : $input;
            $process = new Process([PHP_BINARY, '-r', $script], base_path(), [...$environment, 'CREDIT_ACTOR' => $actor->uuid,
                'CREDIT_DATA' => json_encode($data, JSON_THROW_ON_ERROR)]);
            $process->setTimeout(90)->start();
            $processes[] = $process;
        }
        $outcomes = [];
        foreach ($processes as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput());
            $outcomes[] = $process->getOutput();
        }
        sort($outcomes);
        expect($outcomes)->toBe($scenario === 'reservations' ? ['conflict', 'ok'] : ['ok', 'ok'])
            ->and(SaasCreditNote::count())->toBe(1)->and(SaasCreditNoteLine::count())->toBe(1)
            ->and(Activity::where('event', SaasCreditNoteService::CREATED)->count())->toBe(1);
        if ($scenario === 'issue') {
            expect(SaasCreditNote::sole()->status)->toBe(DocumentStatusEnum::ISSUED)
                ->and(Activity::where('event', SaasInvoiceIssuanceService::CREDIT_ISSUED)->count())->toBe(1)
                ->and(SaasCreditNote::sole()->deliveries()->count())->toBe(1)
                ->and(BillingSequence::where('document_type', DocumentTypeEnum::CREDIT_NOTE)->sole()->next_number)->toBe(2);
        }
    } finally {
        foreach ($processes as $process) {
            $process->stop();
        }
        DB::beginTransaction();
    }
})->with(['reservations', 'same-operation', 'issue']);

test('invalid corrections never reserve quantity or money', function (string $quantity, string $amount): void {
    $f = creditNoteFixture();
    expect(fn () => app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f, $amount, $quantity)))
        ->toThrow(ValidationException::class);
    expect(SaasCreditNote::count())->toBe(0)->and(SaasCreditNoteLine::count())->toBe(0);
})->with([['0', '1.00'], ['-1', '1.00'], ['0.10', '-1.00'], ['0.10', '1.001'], ['1.001', '1.00']]);

test('credit permissions are independent and invoice issuance never grants credit issuance', function (string $permission): void {
    $f = creditNoteFixture();
    $credit = app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f));
    $staff = User::factory()->create();
    $staff->assignRole(Role::createWithPermissions('credit-'.Str::random(8), [$permission => 7]));
    expect(Gate::forUser($staff)->allows('view', $credit))->toBe($permission === 'saas.credit-notes.view')
        ->and(Gate::forUser($staff)->allows('create', SaasCreditNote::class))->toBe($permission === 'saas.credit-notes.create')
        ->and(Gate::forUser($staff)->allows('issue', $credit))->toBe($permission === 'saas.credit-notes.issue')
        ->and(Gate::forUser($staff)->allows('viewOwn', SaasCreditNote::class))->toBeFalse();
})->with(['saas.credit-notes.view', 'saas.credit-notes.create', 'saas.credit-notes.issue', 'saas.invoices.issue']);

test('the future refund journal reduces retained funds and reserves uncertain refunds without creating a refund workflow', function (): void {
    $f = creditNoteFixture();
    $payment = app(SaasPaymentService::class)->recordCashPayment($f['actor'], $f['invoice'], [...manualExternalPayload(), 'received' => true]);
    $credit = app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f, '1000.00', '0.50'));
    $credit = app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $credit, $credit->managementState());
    $refund = new SaasPayment;
    $refund->forceFill(['record_type' => InvoicePaymentService::REFUND_RECORD_TYPE, 'document_id' => $f['invoice']->id, 'user_id' => $f['owner']->id,
        'original_payment_id' => $payment->id, 'credit_note_id' => $credit->id, 'created_by_id' => $f['actor']->id,
        'operation_key' => 'saas:refund:test:'.Str::uuid(), 'correlation_id' => (string) Str::uuid(), 'transfer_method' => 1,
        'transfer_status' => TransferStatusEnum::UNCERTAIN, 'refund_reason' => 1, 'amount' => '300.00', 'currency' => 'DZD', 'reason' => 'Fixture réservée au futur module'])->save();
    $balances = app(InvoicePaymentService::class);
    expect($balances->balance($f['owner'], $f['invoice'])['refunds_reserved'])->toBe('300.00')
        ->and($balances->balance($f['owner'], $f['invoice'])['surplus'])->toBe('1000.00');
    $refund->forceFill(['transfer_status' => TransferStatusEnum::VERIFIED, 'proof_media_id' => $payment->getAttribute('proof_media_id'),
        'transfer_reference' => 'REFUND-FIXTURE', 'financial_account_key' => 'TEST', 'transaction_fingerprint' => hash('sha256', (string) Str::uuid()),
        'occurred_at' => now(), 'validated_by_id' => $f['actor']->id, 'validated_at' => now(), 'performed_by_id' => $f['actor']->id, 'sending_started_at' => now()])->save();
    $balance = $balances->balance($f['owner'], $f['invoice']);
    expect($balance['net_due'])->toBe('2000.00')->and($balance['payments'])->toBe('3000.00')->and($balance['refunds'])->toBe('300.00')
        ->and($balance['funds_retained'])->toBe('2700.00')->and($balance['surplus'])->toBe('700.00')->and($balance['refunds_reserved'])->toBe('0.00');
});

test('a reversed payment and its verified negative inverse cancel each other and do not create a surplus', function (): void {
    $f = creditNoteFixture();
    $payment = app(SaasPaymentService::class)->recordCashPayment($f['actor'], $f['invoice'], [...manualExternalPayload(), 'received' => true]);
    $payment->forceFill(['transfer_status' => TransferStatusEnum::REVERSED])->save();
    expect(fn () => app(InvoicePaymentService::class)->balance($f['owner'], $f['invoice']))->toThrow(ValidationException::class);
    $inverse = new SaasPayment;
    $inverse->forceFill([...$payment->only(['record_type', 'document_id', 'user_id', 'transfer_method', 'proof_media_id', 'financial_account_key',
        'validated_by_id', 'validated_at', 'occurred_at']), 'amount' => '-3000.00', 'currency' => 'DZD', 'reversal_of_id' => $payment->id,
        'operation_key' => 'saas:payment:inverse:'.Str::uuid(), 'correlation_id' => (string) Str::uuid(), 'transfer_status' => TransferStatusEnum::VERIFIED,
        'transfer_reference' => 'INVERSE-FIXTURE', 'transaction_fingerprint' => hash('sha256', (string) Str::uuid())])->save();
    $balance = app(InvoicePaymentService::class)->balance($f['owner'], $f['invoice']);
    expect($balance['payments'])->toBe('0.00')->and($balance['remaining'])->toBe('3000.00')->and($balance['surplus'])->toBe('0.00');
});

test('credit services reject an initialized tenant without changing its canary or central documents', function (): void {
    $f = creditNoteFixture();
    Event::fake([TenantCreated::class]);
    $tenant = Tenant::factory()->create(['slug' => 'credit-'.Str::lower(Str::random(8))]);
    $database = $tenant->database()->getName();
    expect($database)->toStartWith(config('tenancy.database.prefix'));
    DB::commit();
    try {
        CreateDatabase::dispatchSync($tenant);
        $tenant->run(function () use ($f): void {
            DB::statement('CREATE TABLE credit_canary (id INT PRIMARY KEY, value INT NOT NULL)');
            DB::table('credit_canary')->insert(['id' => 1, 'value' => 42]);
            expect(fn () => app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $f['invoice'], []))->toThrow(HttpException::class);
            expect(fn () => app(InvoicePaymentService::class)->balance($f['actor'], $f['invoice']))->toThrow(HttpException::class);
            expect(DB::table('credit_canary')->sole()->value)->toBe(42)->and(DB::getSchemaBuilder()->hasTable('saas_invoices'))->toBeFalse();
        });
        expect(SaasCreditNote::count())->toBe(0);
    } finally {
        tenancy()->end();
        DB::purge('tenant');
        TestCase::dropIsolatedMysqlDatabase($database);
        DB::beginTransaction();
    }
});

test('credit permission seeding is additive and preserves existing identities and grants', function (): void {
    $f = creditNoteFixture();
    $staff = User::factory()->create();
    $staff->assignRole(Role::createWithPermissions('credit-seed-'.Str::random(8), ['saas.credit-notes.create' => 7]));
    $before = DB::table('model_has_permissions')->where('model_id', $staff->id)->get()->toJson();
    $permission = Permission::where('name', 'saas.credit-notes.create')->sole();
    $this->seed(CreditNotePermissionSeeder::class);
    $this->seed(CreditNotePermissionSeeder::class);
    expect(Permission::where('name', 'saas.credit-notes.create')->sole()->id)->toBe($permission->id)
        ->and(Permission::where('name', 'like', 'saas.credit-notes.%')->count())->toBe(3)
        ->and(DB::table('model_has_permissions')->where('model_id', $staff->id)->get()->toJson())->toBe($before);
});

test('an issued credit preserves already active subscription rights and its historical payment receipt', function (): void {
    $f = creditNoteFixture();
    $payment = app(SaasPaymentService::class)->recordCashPayment($f['actor'], $f['invoice'], [...manualExternalPayload(), 'received' => true]);
    $before = $f['subscription']->refresh()->getAttributes();
    $credit = app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f));
    app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $credit, $credit->managementState());
    expect($f['subscription']->refresh()->getAttributes())->toBe($before)
        ->and(app(InvoicePaymentService::class)->confirmedPayment($f['owner'], $f['invoice'])->getKey())->toBe($payment->id);
    $this->actingAs($f['owner'], 'central')->get(URL::temporarySignedRoute('owner.payments.receipt', now()->addMinutes(5), ['payment' => $payment]))->assertOk();
});

test('a corrupted PDF cannot be reused to finalize a numbered credit', function (): void {
    $f = creditNoteFixture();
    $credits = app(SaasCreditNoteService::class);
    $credit = $credits->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f));
    $credit = app(SaasNumberingService::class)->reserveNextNumber($f['actor'], $credit, $credit->managementState(), $credits->assertIssuable(...));
    $pdf = app(SaasInvoicePdfService::class);
    $file = $pdf->generate($credit);
    $bytes = Storage::disk('local')->get($file['storage_key']);
    Storage::disk('local')->put($file['storage_key'], str_replace('%AYDRA-SEAL:', '%INVALID-SEAL:', $bytes));
    expect(fn () => app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $credit, $credit->managementState()))->toThrow(ValidationException::class);
    expect($credit->refresh()->status)->toBe(DocumentStatusEnum::PREPARING)->and($credit->deliveries()->count())->toBe(0);
    Storage::disk('local')->put($file['storage_key'], $bytes);
    $credit = app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $credit, $credit->managementState());
    expect($credit->media->file_hash)->toBe($file['file_hash']);
});

test('a credit-only reader sees reservations without forbidden invoice links or mutation buttons', function (): void {
    $f = creditNoteFixture();
    $credit = app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f));
    $reader = User::factory()->create();
    $reader->assignRole(Role::createWithPermissions('credit-read-'.Str::random(8), ['saas.credit-notes.view' => 7]));
    $this->actingAs($reader, 'central')->get(route('central.credit-notes.show', $credit))->assertOk()
        ->assertSee('Disponible')->assertSee('0.90')->assertDontSee('Modifier le brouillon')->assertDontSee('Facture originale');
    $this->get(route('central.invoices.show', $f['invoice']))->assertForbidden();
});

test('SQL foreign keys reject a credit line from another invoice even when application validation is bypassed', function (): void {
    $f = creditNoteFixture();
    $foreign = creditNoteFixture();
    $credit = app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $f['invoice'], creditNoteInput($f));
    $line = $credit->lines()->sole();
    $foreignLine = $foreign['invoice']->lines()->sole();
    $forged = new SaasCreditNoteLine;
    $forged->forceFill([...$line->only(['document_id', 'user_id', 'document_type', 'original_invoice_id', 'quantity', 'description', 'reason',
        'net_amount', 'taxes', 'tax_amount', 'total_amount', 'correlation_id']),
        'line_number' => 2, 'original_invoice_line_id' => $foreignLine->id, 'operation_key' => 'saas:line:forged:'.Str::uuid()]);
    expect(fn () => $forged->save())->toThrow(QueryException::class);
    expect($credit->lines()->count())->toBe(1)->and($credit->originalInvoice->id)->toBe($f['invoice']->id);
    $this->actingAs($f['actor'], 'central')->get(route('central.credit-notes.show', $f['invoice']))->assertNotFound();
    $this->actingAs($foreign['owner'], 'central')->get(route('owner.credit-notes.show', $credit))->assertForbidden();
});

test('the balance refuses historical transfers in a different currency instead of adding unlike money', function (): void {
    $f = creditNoteFixture();
    (new SaasPayment)->forceFill(['record_type' => SaasPayment::RECORD_TYPE, 'user_id' => $f['owner']->id, 'document_id' => $f['invoice']->id,
        'amount' => '100.00', 'currency' => 'EUR', 'transfer_status' => TransferStatusEnum::DECLARED, 'transfer_method' => 1,
        'operation_key' => 'saas:payment:foreign:'.Str::uuid(), 'correlation_id' => (string) Str::uuid()])->save();
    expect(fn () => app(InvoicePaymentService::class)->balance($f['owner'], $f['invoice']))->toThrow(ValidationException::class);
});

test('several original lines keep their own correction caps including original quantities and discounts', function (): void {
    $f = creditNoteFixture(issued: false);
    $invoice = $f['invoice'];
    $amounts = app(InvoiceCalculator::class)->calculate([
        ['description' => 'Prestation avec remise', 'quantity' => '2.00', 'net_unit_price' => '1000.00', 'net_discount' => '500.00'],
        ['description' => 'Autre prestation', 'quantity' => '1.00', 'net_unit_price' => '1500.00', 'net_discount' => '0.00'],
    ], []);
    $invoice->lines()->delete();
    foreach ($amounts['lines'] as $index => $values) {
        (new SaasInvoiceLine)->forceFill([...$values, 'document_id' => $invoice->id, 'user_id' => $invoice->user_id,
            'document_type' => DocumentTypeEnum::INVOICE, 'line_number' => $index + 1, 'operation_key' => 'saas:line:multi:'.Str::uuid(),
            'correlation_id' => $invoice->getAttribute('correlation_id')])->save();
    }
    $invoice = app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $invoice, $invoice->managementState());
    $original = $invoice->lines()->get();
    $input = ['billing_rule_uuid' => $f['creditRule']->uuid, 'operation_id' => (string) Str::uuid(), 'expected_state' => $invoice->managementState(),
        'reason' => 'Correction de deux lignes', 'lines' => [
            ['original_line_uuid' => $original[0]->uuid, 'quantity' => '1.00', 'total_amount' => '1000.00', 'reason' => 'Première prestation'],
            ['original_line_uuid' => $original[1]->uuid, 'quantity' => '0.50', 'total_amount' => '500.00', 'reason' => 'Deuxième prestation'],
        ]];
    $credit = app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $invoice, $input);
    $credit = app(SaasInvoiceIssuanceService::class)->issue($f['actor'], $credit, $credit->managementState());
    expect($credit->lines()->count())->toBe(2)->and($credit->total_amount)->toBe('1500.00')
        ->and($invoice->lines()->first()->getAttribute('net_discount'))->toBe('500.00');
    $input['operation_id'] = (string) Str::uuid();
    $input['lines'] = [['original_line_uuid' => $original[0]->uuid, 'quantity' => '0.10', 'total_amount' => '1000.00', 'reason' => 'Dépassement propre à cette ligne']];
    expect(fn () => app(SaasCreditNoteService::class)->createCreditNoteDraft($f['actor'], $invoice, $input))->toThrow(ValidationException::class);
    expect(SaasCreditNote::count())->toBe(1);
});

test('browser creates prepares emits and privately reads a credit without rewriting its invoice', function (): void {
    $f = creditNoteFixture();
    $runtime = storage_path('framework/testing/module20-browser-'.Str::uuid());
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
        'SESSION_COOKIE' => 'module20_'.Str::random(12), 'LARAVEL_STORAGE_PATH' => $runtime, 'MAIL_MAILER' => 'log',
        'APP_CONFIG_CACHE' => $runtime.'/config.php', 'APP_ROUTES_CACHE' => $runtime.'/routes.php'];
    DB::commit();
    $server = new Process([PHP_BINARY, 'artisan', 'serve', '--host=127.0.0.1', '--port='.$port, '--no-reload', '--no-interaction'], base_path(), $environment);
    $server->setTimeout(null);
    try {
        $server->start();
        File::put($runtime.'/ready.json', json_encode(['url' => $base, 'invoice' => $f['invoice']->uuid,
            'admin' => $f['actor']->email, 'owner' => $f['owner']->email], JSON_THROW_ON_ERROR));
        $deadline = microtime(true) + 900;
        while (! is_file($runtime.'/complete.json') && microtime(true) < $deadline && $server->isRunning()) {
            usleep(200000);
        }
        expect(is_file($runtime.'/complete.json'))->toBeTrue($server->getErrorOutput());
        $credit = SaasCreditNote::sole();
        expect($credit->status)->toBe(DocumentStatusEnum::ISSUED)->and($credit->total_amount)->toBe('500.00')
            ->and($credit->deliveries()->count())->toBe(1)->and($f['invoice']->refresh()->total_amount)->toBe('3000.00');
    } finally {
        $server->stop();
        DB::beginTransaction();
        File::deleteDirectory($runtime);
    }
})->skip(fn (): bool => getenv('AYDRA_MODULE20_BROWSER') !== '1', 'Requires an interactive browser pilot on isolated data.');
