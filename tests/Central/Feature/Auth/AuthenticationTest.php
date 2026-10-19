<?php

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

function approvedAuthenticationMerchant(array $attributes = []): User
{
    $validator = User::factory()->create();

    return User::factory()->create([
        'legal_profile_version' => 1, 'legal_verification_status' => \App\Enums\VerificationStatusEnum::VERIFIED,
        'legal_verified_at' => now(), 'legal_verified_by_id' => $validator->id, ...$attributes,
    ]);
}

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = approvedAuthenticationMerchant();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('owner.first-shop'));

    $this->assertAuthenticatedAs($user, 'central');
    $this->assertGuest('tenant');
});

test('the central super admin can log in and open the dashboard', function (): void {
    $user = User::factory()->create();
    $role = new Role(['name' => 'root']);
    $role->forceFill(['is_system' => true, 'is_protected' => true, 'is_super_admin' => true])->save();
    $user->assignRole($role);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user, 'central');
    $this->assertGuest('tenant');
    $this->get(route('dashboard'))->assertOk();
});

test('login accepts an email with surrounding spaces and uppercase letters', function (): void {
    $user = approvedAuthenticationMerchant(['email' => 'merchant@example.test']);

    $this->post(route('login.store'), [
        'email' => '  MERCHANT@EXAMPLE.TEST  ',
        'password' => 'password',
    ])->assertSessionHasNoErrors()->assertRedirect(route('owner.first-shop'));

    $this->assertAuthenticatedAs($user, 'central');
});

test('login updates an outdated password hash without changing the password', function (): void {
    $user = approvedAuthenticationMerchant(['password' => Hash::make('password', ['rounds' => 4])]);
    $oldHash = $user->password;
    config(['hashing.bcrypt.rounds' => 5, 'hashing.rehash_on_login' => true]);
    Hash::forgetDrivers();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasNoErrors()->assertRedirect(route('owner.first-shop'));

    $newHash = $user->fresh()->password;
    expect($newHash)->not->toBe($oldHash);
    expect(Hash::check('password', $newHash))->toBeTrue();
    expect(Hash::needsRehash($newHash))->toBeFalse();
    $this->assertAuthenticatedAs($user, 'central');
});

test('login rejects an unknown central account with the same credentials error', function (): void {
    $this->post(route('login.store'), [
        'email' => 'missing@example.test',
        'password' => 'password',
    ])->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest('central');
});

test('central login respects administrative account status', function (StatusEnum $status): void {
    $user = User::factory()->create(['status' => $status]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    if (in_array($status, [StatusEnum::PENDING, StatusEnum::REJECTED], true)) {
        $response->assertSessionHasNoErrors()->assertRedirect(route('registration.status'));
        $this->assertAuthenticatedAs($user, 'central');
        $this->get(route('registration.status'))->assertOk();

        return;
    }
    $response->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest('central');
})->with([
    'inactive' => [StatusEnum::INACTIVE],
    'suspended' => [StatusEnum::SUSPENDED],
    'deleted status' => [StatusEnum::DELETED],
    'awaiting approval' => [StatusEnum::PENDING],
    'refused registration' => [StatusEnum::REJECTED],
]);

test('soft deleted central accounts cannot log in with a valid password', function (): void {
    $user = User::factory()->create();
    $user->delete();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest('central');
});

test('a suspended account cannot start the two factor login challenge', function (): void {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    $user = User::factory()->withTwoFactor()->create(['status' => StatusEnum::SUSPENDED]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors(['email' => __('auth.failed')])->assertSessionMissing('login.id');

    $this->assertGuest('central');
});

test('login attempts for the same normalized email are rate limited', function (): void {
    $user = User::factory()->create(['email' => 'merchant@example.test']);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), [
            'email' => $attempt % 2 === 0 ? '  MERCHANT@EXAMPLE.TEST  ' : $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['email']);
    }

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertTooManyRequests();

    $this->assertGuest('central');
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest('central');
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('a pending session is limited to email verification and registration guidance', function (string $routeName): void {
    $user = User::factory()->unverified()->create(['status' => StatusEnum::PENDING]);
    $response = $this->actingAs($user, 'central')->get(route($routeName));
    if ($routeName === 'verification.notice') {
        $response->assertOk();
    } else {
        $response->assertRedirect(route('verification.notice'));
    }
    $this->assertAuthenticatedAs($user, 'central');
})->with(['profile.edit', 'password.confirm', 'verification.notice', 'dashboard']);

test('an account disabled during its two factor challenge does not remain authenticated', function (): void {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    $user = User::factory()->withTwoFactor()->create(['status' => StatusEnum::REJECTED]);
    $this->withSession(['login.id' => $user->id, 'login.remember' => true])
        ->post(route('two-factor.login.store'), ['recovery_code' => 'recovery-code-1'])->assertForbidden();
    $this->assertGuest('central');
});

test('passkey authorization requires a currently active central account', function (StatusEnum $status, bool $allowed): void {
    $user = User::factory()->create(['status' => $status]);
    $passkey = new Passkey;
    $passkey->setRelation('user', $user);
    expect(Passkeys::allowsLogin(Request::create('/passkeys/login', 'POST'), $passkey))->toBe($allowed);
})->with([
    'active' => [StatusEnum::ACTIVE, true],
    'pending' => [StatusEnum::PENDING, false],
    'refused' => [StatusEnum::REJECTED, false],
    'suspended' => [StatusEnum::SUSPENDED, false],
]);
