<?php

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\Countries\Country;
use App\Models\Central\Tenants\Tenant;
use App\Models\User;
use App\Services\Central\Auth\RegistrationVerificationNotification;
use Database\Seeders\Central\CountrySeeder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\TransportInterface;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    Notification::fake();
    $this->seed(CountrySeeder::class);
    $response = $this->post(route('register.store'), [
        'first_name' => 'John', 'last_name' => 'Doe',
        'email' => 'test@example.com',
        'phone' => '0779107446',
        'password' => 'password',
        'password_confirmation' => 'password',
        'country_uuid' => Country::where('code', 'DZ')->firstOrFail()->uuid,
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('registration_created');

    $this->assertAuthenticated('central');
    $user = User::where('email', 'test@example.com')->firstOrFail();
    expect($user->status)->toBe(StatusEnum::PENDING);
    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
    Notification::assertSentTimes(VerifyEmail::class, 1);
    $this->get(route('verification.notice'))->assertOk()
        ->assertSee('registration-created', false)->assertSee('Votre compte Aydra a été créé avec succès !')
        ->assertSee('Renvoyer l’e-mail de vérification');
    expect(Tenant::count())->toBe(0);
});

test('public registration cannot inject an approved status or privileged fields', function (): void {
    Notification::fake();
    $this->seed(CountrySeeder::class);
    $this->postJson(route('register.store'), [
        'last_name' => 'Public', 'email' => 'public@example.test', 'phone' => '0779107446',
        'password' => 'password', 'password_confirmation' => 'password',
        'country_uuid' => Country::where('code', 'DZ')->firstOrFail()->uuid,
        'status' => 1, 'email_verified_at' => now()->toDateTimeString(), 'roles' => ['root'],
    ])->assertCreated()->assertJsonPath('status', 5);
    $this->assertAuthenticated('central');
    $target = User::where('email', 'public@example.test')->firstOrFail();
    expect($target->status)->toBe(StatusEnum::PENDING);
    expect($target->hasVerifiedEmail())->toBeFalse();
    expect($target->roles()->exists())->toBeFalse();
    expect($target->phone)->toBe('+213779107446');
    Notification::assertSentTo($target, VerifyEmail::class);
});

test('registration survives SMTP failure without claiming that verification mail was sent', function (bool $json): void {
    $this->seed(CountrySeeder::class);
    $transport = Mockery::mock(TransportInterface::class);
    $transport->shouldReceive('send')->once()->andThrow(new TransportException('private-smtp-secret'));
    Mail::mailer()->setSymfonyTransport($transport);
    Log::spy();
    $payload = [
        'last_name' => 'Pending', 'email' => 'mail-failure@example.test', 'phone' => '0779107446',
        'password' => 'password', 'password_confirmation' => 'password',
        'country_uuid' => Country::where('code', 'DZ')->firstOrFail()->uuid,
    ];

    $response = $json ? $this->postJson(route('register.store'), $payload) : $this->post(route('register.store'), $payload);
    if ($json) {
        $response->assertCreated()->assertJsonPath('status', StatusEnum::PENDING->value)
            ->assertJsonPath('email_sent', false)->assertJsonPath('redirect', route('verification.notice'));
    } else {
        $response->assertRedirect(route('verification.notice'))->assertSessionHasNoErrors()
            ->assertSessionHas('registration_created', true)->assertSessionHas('registration_email_sent', false);
    }
    $response->assertDontSee('private-smtp-secret');
    $user = User::where('email', $payload['email'])->firstOrFail();
    expect($user->status)->toBe(StatusEnum::PENDING)->and($user->hasVerifiedEmail())->toBeFalse();
    $this->assertAuthenticatedAs($user, 'central');
    Log::shouldHaveReceived('warning')->once()->with('Central account email delivery failed.', [
        'operation' => 'verification', 'user_uuid' => $user->uuid, 'exception_class' => TransportException::class,
    ]);
})->with(['web form' => [false], 'JSON registration' => [true]]);

test('registered notifications skip verified central accounts', function (): void {
    $user = User::factory()->create();
    Notification::fake();

    app(RegistrationVerificationNotification::class)
        ->handle(new Registered($user));

    Notification::assertNothingSent();
});
