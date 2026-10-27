<?php

use App\Enums\Central\Users\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Roles\Role;
use App\Models\Central\Tenants\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use Stancl\Tenancy\Events\TenantCreated;

function approvedAuthenticationMerchant(array $attributes = []): User
{
    $validator = User::factory()->create();

    return User::factory()->create([
        'legal_profile_version' => 1, 'legal_verification_status' => VerificationStatusEnum::VERIFIED,
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

test('login responses only resume intended URLs allowed by the account journey', function (string $state, string $target, string $expected): void {
    $user = in_array($state, ['pending', 'rejected'], true)
        ? User::factory()->create(['status' => $state === 'pending' ? StatusEnum::PENDING : StatusEnum::REJECTED])
        : approvedAuthenticationMerchant();
    if ($state === 'ready') {
        Event::fake([TenantCreated::class]);
        Tenant::factory()->create(['user_id' => $user->id,
            'status' => App\Enums\Central\Tenants\StatusEnum::ACTIVE,
            'schema_version' => 'completed-schema', 'provisioned_at' => now()]);
        Event::assertDispatched(TenantCreated::class);
    }
    $intended = match ($target) {
        'internal' => route('appearance.edit'),
        'external' => 'https://outside.example/path',
        'lookalike' => 'http://aydra.localhost.outside.example/path',
        'tenant' => 'http://boutique.aydra.localhost/',
        'verification' => URL::temporarySignedRoute('verification.verify', now()->addMinutes(30),
            ['id' => $user->id, 'hash' => sha1($user->email)]),
    };

    $this->withSession(['url.intended' => $intended])->postJson(route('login.store'), [
        'email' => $user->email, 'password' => 'password',
    ])->assertOk()->assertJsonPath('two_factor', false)
        ->assertJsonPath('redirect', $expected === 'intended' ? $intended : route($expected));
    $this->assertAuthenticatedAs($user, 'central');
})->with([
    'ready account resumes an internal URL' => ['ready', 'internal', 'intended'],
    'first shop cannot be skipped' => ['setup', 'internal', 'owner.first-shop'],
    'pending account stays limited' => ['pending', 'internal', 'registration.status'],
    'rejected account stays limited' => ['rejected', 'internal', 'registration.status'],
    'external URL is ignored' => ['ready', 'external', 'dashboard'],
    'lookalike central host is ignored' => ['ready', 'lookalike', 'dashboard'],
    'tenant URL is ignored' => ['ready', 'tenant', 'dashboard'],
    'limited account resumes verification' => ['pending', 'verification', 'intended'],
]);

test('login computes the merchant journey once when an internal URL was remembered', function (): void {
    $user = approvedAuthenticationMerchant();
    $connection = $user->getConnection();
    $connection->enableQueryLog();
    try {
        $this->withSession(['url.intended' => route('appearance.edit')])->postJson(route('login.store'), [
            'email' => $user->email, 'password' => 'password',
        ])->assertOk()->assertJsonPath('redirect', route('owner.first-shop'));
        $queries = $connection->getQueryLog();
    } finally {
        $connection->disableQueryLog();
        $connection->flushQueryLog();
    }
    expect(collect($queries)->filter(fn (array $query): bool => str_contains($query['query'], 'from `tenants`')))->toHaveCount(1);
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
