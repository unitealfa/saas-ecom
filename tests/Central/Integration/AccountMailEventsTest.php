<?php

use App\Enums\Central\Users\StatusEnum;
use App\Events\Central\Auth\AccountInvitationRequested;
use App\Events\Central\Auth\StaffInvitationResent;
use App\Listeners\Central\Auth\SendAccountInvitation;
use App\Models\Central\Users\User;
use App\Notifications\Central\Auth\AccountInvitation;
use App\Notifications\Central\Auth\AccountMailFailureNotification;
use App\Services\AccountMailEvents;
use App\Services\Central\Auth\AccountMailDelivery;
use Database\Seeders\Central\CountrySeeder;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

pest()->extend(TestCase::class);

beforeEach(function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
    $this->seed(CountrySeeder::class);

    config(['queue.account_mail_connection' => 'sync']);
});

test('account mail dispatch is refused during a transaction and accepted after commit', function (): void {
    Event::fake([AccountInvitationRequested::class]);

    $user = User::factory()->create();
    $mail = app(AccountMailDelivery::class);

    DB::transaction(function () use ($mail, $user): void {
        expect($mail->invitation($user, 'test-token'))->toBeFalse();

        Event::assertNotDispatched(AccountInvitationRequested::class);
    });

    expect($mail->invitation($user, 'test-token'))->toBeTrue();

    Event::assertDispatchedTimes(AccountInvitationRequested::class, 1);
});

test('rollback never dispatches an account invitation from inside the transaction', function (): void {
    Event::fake([AccountInvitationRequested::class]);

    $user = User::factory()->create();

    expect(fn () => DB::transaction(function () use ($user): void {
        expect(app(AccountMailDelivery::class)->invitation($user, 'test-token'))->toBeFalse();

        throw new LogicException('rollback');
    }))->toThrow(LogicException::class, 'rollback');

    Event::assertNotDispatched(AccountInvitationRequested::class);
});

test('sync account invitations still send the existing notification immediately', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    expect(app(AccountMailDelivery::class)->invitation($user, 'test-token'))->toBeTrue();

    Notification::assertSentTo(
        User::findOrFail($user->id),
        AccountInvitation::class,
        fn ($mail): bool => $mail->token === 'test-token',
    );
});

test('database staff invitation uses the default encrypted queued listener', function (): void {
    config([
        'queue.account_mail_connection' => 'database',
        'queue.connections.database.connection' => config('tenancy.database.central_connection'),
    ]);

    Queue::fake();
    Notification::fake();

    $user = User::factory()->create();

    expect(
        app(AccountMailDelivery::class)->invitation(
            $user,
            'test-token',
            staffResend: true,
        ),
    )->toBeTrue();

    Queue::assertPushedOn(
        'default',
        CallQueuedListener::class,
        fn ($job): bool => $job->class === SendAccountInvitation::class
            && $job->data[0] instanceof StaffInvitationResent
            && $job->shouldBeEncrypted
            && $job->tries === 3,
    );

    Notification::assertNothingSent();
});

test('a failed account invitation reports only safe data to the central author', function (): void {
    Notification::fake();

    $author = User::factory()->create([
        'status' => StatusEnum::ACTIVE,
        'email_verified_at' => now(),
    ]);

    $target = User::factory()->create();

    $event = new AccountInvitationRequested(
        $target->uuid,
        $target->email,
        'secret-token',
        'https://example.test/secret-token',
        now()->addHour()->getTimestamp(),
        $author->uuid,
    );

    (new SendAccountInvitation)->failed(
        $event,
        new RuntimeException('secret-token'),
    );

    Notification::assertSentTo(
        User::findOrFail($author->id),
        AccountMailFailureNotification::class,
        function ($notification, $channels) use ($author): bool {
            $data = json_encode($notification->toDatabase($author));

            return $channels === ['database']
                && ! str_contains($data, 'secret-token')
                && ! str_contains($data, 'https://');
        },
    );

    Notification::assertNotSentTo(
        User::findOrFail($target->id),
        AccountMailFailureNotification::class,
    );
});

test('a dispatch exception does not escape the account mail boundary', function (): void {
    expect(
        AccountMailEvents::dispatch(
            'test_failure',
            fn () => throw new RuntimeException('private-link'),
        ),
    )->toBeFalse();
});