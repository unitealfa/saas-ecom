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
        'password' => 'password',
        'password_confirmation' => 'password',
        'country_uuid' => Country::where('code', 'DZ')->firstOrFail()->uuid,
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    $this->assertGuest('central');
    $user = User::where('email', 'test@example.com')->firstOrFail();
    expect($user->status)->toBe(StatusEnum::PENDING);
    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('public registration cannot inject an approved status or privileged fields', function (): void {
    Notification::fake();
    $this->seed(CountrySeeder::class);
    $this->postJson(route('register.store'), [
        'last_name' => 'Public', 'email' => 'public@example.test', 'phone' => '0123456789',
        'password' => 'password', 'password_confirmation' => 'password',
        'country_uuid' => Country::where('code', 'DZ')->firstOrFail()->uuid,
        'status' => 1, 'email_verified_at' => now()->toDateTimeString(), 'roles' => ['root'],
    ])->assertCreated()->assertJsonPath('status', 5);
    $this->assertGuest('central');
    $target = User::where('email', 'public@example.test')->firstOrFail();
    expect($target->status)->toBe(StatusEnum::PENDING);
    expect($target->hasVerifiedEmail())->toBeFalse();
    expect($target->roles()->exists())->toBeFalse();
    expect($target->phone)->toBe('0123456789');
    Notification::assertSentTo($target, VerifyEmail::class);
});
