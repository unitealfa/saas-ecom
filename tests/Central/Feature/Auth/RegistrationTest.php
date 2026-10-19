<?php

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\Country;
use App\Models\User;
use Database\Seeders\Central\CountrySeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

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
    $this->get(route('verification.notice'))->assertOk()
        ->assertSee('registration-created', false)->assertSee('Votre compte Aydra a été créé avec succès !')
        ->assertSee('Renvoyer l’e-mail de vérification');
    expect(\App\Models\Central\Tenant::count())->toBe(0);
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
