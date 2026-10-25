<?php

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\Role;
use App\Models\User;
use App\Services\Central\Auth\AccountMailDelivery;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\TransportInterface;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertOk();
});

test('controller corrections return verification delivery failures to the verification page', function (): void {
    $user = User::factory()->unverified()->create();
    $this->mock(AccountMailDelivery::class)
        ->shouldReceive('verification')->once()->andReturn(false);

    $this->actingAs($user, 'central')->from('https://external.example/previous')
        ->post(route('verification.send'))->assertRedirect(route('verification.notice'))
        ->assertSessionHasErrors('email');
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verification resend reports SMTP failure without verifying or disconnecting the account', function (): void {
    $user = User::factory()->unverified()->create(['status' => StatusEnum::PENDING]);
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldReceive('send')->once()
        ->andThrow(new TransportException('private-smtp-secret'));
    Mail::mailer()->setSymfonyTransport($transport);
    Log::spy();

    $this->actingAs($user, 'central')->postJson(route('verification.send'))
        ->assertStatus(503)->assertJsonPath('message', 'L’e-mail n’a pas pu être envoyé. Réessayez dans quelques instants.')
        ->assertDontSee('private-smtp-secret');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    $this->assertAuthenticatedAs($user, 'central');
});

test('unverified users are redirected to the email verification prompt', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('appearance.edit'));

    $response->assertRedirect(route('verification.notice'));
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('login'));
    $this->assertGuest('central');
});

test('verified email responses preserve the native staff session', function (bool $json): void {
    $user = User::factory()->unverified()->create();
    $role = new Role(['name' => 'root']);
    $role->forceFill(['is_system' => true, 'is_protected' => true, 'is_super_admin' => true])->save();
    $user->assignRole($role);
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30),
        ['id' => $user->id, 'hash' => sha1($user->email)]);

    $this->actingAs($user, 'central');
    $response = $json ? $this->getJson($url) : $this->get($url);
    if ($json) {
        $response->assertNoContent();
    } else {
        $response->assertRedirect(route('dashboard').'?verified=1');
    }
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->assertAuthenticatedAs($user, 'central');
})->with(['web staff response' => [false], 'JSON staff response' => [true]]);

test('verified email JSON responses disconnect merchants after proving email ownership', function (): void {
    $user = User::factory()->unverified()->create(['status' => StatusEnum::PENDING]);
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30),
        ['id' => $user->id, 'hash' => sha1($user->email)]);

    $this->actingAs($user, 'central')->getJson($url)->assertNoContent();
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->assertGuest('central');
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')],
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('already verified user visiting verification link is redirected without firing event again', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('login'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertNotDispatched(Verified::class);
});

test('an expired verification link gives guidance without verifying the address', function (): void {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $user->id, 'hash' => sha1($user->email)]);
    $this->actingAs($user, 'central')->get($url)->assertForbidden()->assertViewIs('central.pages.auth.verification-error');
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verification resend sends a notification and repeated requests are throttled', function (): void {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $this->actingAs($user, 'central')->post(route('verification.send'))->assertSessionHasNoErrors();
    Notification::assertSentTo($user, VerifyEmail::class);
    foreach (range(1, 5) as $attempt) {
        $this->post(route('verification.send'));
    }
    $this->post(route('verification.send'))->assertTooManyRequests();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
