<?php

use App\DTOs\Central\Users\UserProfileData;
use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\Country;
use App\Models\Central\Role;
use App\Models\User;
use App\Services\Central\Users\ContactMigrationAudit;
use App\Services\Central\Users\PhoneNumberNormalizer;
use App\Services\Central\Users\UserManagementService;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\CountrySeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed([CountrySeeder::class, AuthorizationSeeder::class]);
    Notification::fake();
    Queue::fake();
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::findByName('root', 'central'));
});

test('Algerian phone representations resolve to the same E164 value', function (string $input): void {
    expect(app(PhoneNumberNormalizer::class)->normalize($input, 'DZ'))->toBe('+213779107446');
})->with(['0779107446', '+213779107446', '0779 10 74 46', '+213 (779) 10-74-46', '00213779107446']);

test('invalid numbers and mismatched regions are rejected', function (string $input, string $region): void {
    expect(fn () => app(PhoneNumberNormalizer::class)->normalize($input, $region))->toThrow(ValidationException::class);
})->with([['123', 'DZ'], ['0779hello', 'DZ'], ['+213779107446', 'FR'], ['0779107446 ext 123', 'DZ']]);

test('a shared calling code never replaces explicit region validation', function (): void {
    $normalizer = app(PhoneNumberNormalizer::class);
    expect($normalizer->callingCode('US'))->toBe('+1')->and($normalizer->callingCode('CA'))->toBe('+1');
    expect($normalizer->normalize('4165551234', 'CA'))->toBe('+14165551234');
    expect(fn () => $normalizer->normalize('+14165551234', 'US'))->toThrow(ValidationException::class);
});

test('historical international phones can retain their own country without changing the profile', function (): void {
    $normalizer = app(PhoneNumberNormalizer::class);
    expect($normalizer->historical('+213779107446', 'SA'))->toBe(['value' => '+213779107446', 'region' => 'DZ']);
    expect(fn () => $normalizer->historical('+14165551234', 'US'))->toThrow(ValidationException::class);
});

test('two accounts cannot own the same normalized phone even after archival', function (): void {
    $owner = User::factory()->create(['phone' => '0779107446']);
    expect(fn () => User::factory()->create(['phone' => '+213779107446']))->toThrow(ValidationException::class);
    $owner->delete();
    expect(fn () => User::factory()->create(['phone' => '0779 10 74 46']))->toThrow(ValidationException::class);
});

test('SQL rejects a concurrent normalized phone claim independently of model validation', function (): void {
    $owner = User::factory()->create(['phone' => '0779107446']);
    $other = User::factory()->create();
    expect(fn () => DB::table('users')->where('id', $other->id)->update([
        'phone' => $owner->phone, 'phone_country_id' => $owner->phone_country_id,
    ]))->toThrow(UniqueConstraintViolationException::class);
    expect($other->refresh()->phone)->toBeNull();
});

test('an email remains unique across accounts after case normalization', function (): void {
    User::factory()->create(['email' => 'owner@example.test']);
    expect(fn () => User::factory()->create(['email' => ' OWNER@EXAMPLE.TEST ']))->toThrow(ValidationException::class);
});

test('changing only the profile country preserves the phone and its region', function (): void {
    $user = User::factory()->create(['phone' => '0779107446']);
    $france = Country::where('code', 'FR')->firstOrFail();
    $france->forceFill(['is_active' => true])->save();
    app(UserManagementService::class)->update($this->admin, $user, new UserProfileData(
        $user->last_name, $user->first_name, $user->email, $user->phone, $france->uuid,
    ));
    expect($user->refresh()->country_id)->toBe($france->id)
        ->and($user->phone)->toBe('+213779107446')->and($user->phoneCountry->code)->toBe('DZ');
});

test('an administrator can replace the phone with one from another active country', function (): void {
    $user = User::factory()->create(['phone' => '0779107446']);
    $france = Country::where('code', 'FR')->firstOrFail();
    $france->forceFill(['is_active' => true])->save();
    $this->actingAs($this->admin, 'central')->put(route('central.users.update', $user), [
        'last_name' => $user->last_name, 'first_name' => $user->first_name, 'email' => $user->email,
        'phone' => '0612345678', 'country_uuid' => $user->country->uuid, 'phone_country_uuid' => $france->uuid,
    ])->assertSessionHasNoErrors();
    expect($user->refresh()->phone)->toBe('+33612345678')
        ->and($user->phoneCountry->code)->toBe('FR')->and($user->country->code)->toBe('DZ');
});

test('an inactive phone country permits retaining but not replacing an existing number', function (): void {
    $user = User::factory()->create(['phone' => '0779107446']);
    $country = $user->phoneCountry;
    $country->forceFill(['is_active' => false])->save();
    $service = app(UserManagementService::class);
    $service->update($this->admin, $user, new UserProfileData(
        $user->last_name, $user->first_name, $user->email, '0779 10 74 46', $country->uuid, [], $country->uuid,
    ));
    expect(fn () => $service->update($this->admin, $user, new UserProfileData(
        $user->last_name, $user->first_name, $user->email, '0555123456', $country->uuid, [], $country->uuid,
    )))->toThrow(ValidationException::class);
    expect($user->refresh()->phone)->toBe('+213779107446');
});

test('the personal profile accepts international phone settings without changing another account', function (): void {
    $user = User::factory()->create(['phone' => '0779107446']);
    $this->actingAs($user, 'central');
    Livewire::test('pages::settings.profile')->set('phone', '0555123456')->call('updateProfileInformation')->assertHasNoErrors();
    expect($user->refresh()->phone)->toBe('+213555123456')->and($user->phone_verified_at)->toBeNull();
    expect(fn () => app(UserManagementService::class)->update($user, $this->admin, new UserProfileData(
        $this->admin->last_name, $this->admin->first_name, $this->admin->email, null, $this->admin->country->uuid,
    ), ability: 'updateOwnProfile'))->toThrow(AuthorizationException::class);
});

test('a phone replacement and duplicate email rejection roll back together', function (): void {
    $owner = User::factory()->create(['phone' => '0779107446']);
    $target = User::factory()->create(['phone' => '0555123456']);
    expect(fn () => app(UserManagementService::class)->update($this->admin, $target, new UserProfileData(
        $target->last_name, $target->first_name, $owner->email, '0661123456', $target->country->uuid,
    )))->toThrow(ValidationException::class);
    expect($target->refresh()->phone)->toBe('+213555123456');
});

test('a replacement email is unverified and invalidates the former reset token', function (): void {
    $user = User::factory()->create(['phone' => '0779107446']);
    $token = Password::broker('central')->createToken($user);
    $oldEmail = $user->email;
    app(UserManagementService::class)->update($this->admin, $user, new UserProfileData(
        $user->last_name, $user->first_name, 'new-owner@example.test', $user->phone, $user->country->uuid,
    ));
    $user->refresh();
    expect($user->hasVerifiedEmail())->toBeFalse()->and(Password::broker('central')->tokenExists($user, $token))->toBeFalse();
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $oldEmail]);
    Notification::assertSentTo($user, VerifyEmail::class);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('verification.notice'));
});

test('personal email changes immediately refresh the authenticated identity and verification state', function (): void {
    $this->actingAs($this->admin, 'central');
    Livewire::test('pages::settings.profile')->set('email', 'new-admin@example.test')->call('updateProfileInformation')->assertHasNoErrors();
    expect(auth('central')->user()->email)->toBe('new-admin@example.test')
        ->and(auth('central')->user()->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($this->admin, VerifyEmail::class);
});

test('user searches support local and formatted phone input only with authorized access', function (): void {
    $user = User::factory()->create(['phone' => '0779107446']);
    $service = app(UserManagementService::class);
    expect($service->search($this->admin, '0779 10 74 46')->getCollection()->contains('id', $user->id))->toBeTrue();
    expect(fn () => $service->search($user, '+213779107446'))->toThrow(AuthorizationException::class);
});

test('public registration requires a phone and rejects inactive selected countries', function (): void {
    $payload = [
        'last_name' => 'Owner', 'email' => 'new@example.test', 'password' => 'password',
        'password_confirmation' => 'password', 'country_uuid' => Country::where('code', 'DZ')->firstOrFail()->uuid,
    ];
    $this->post(route('register.store'), $payload)->assertSessionHasErrors('phone');
    $this->post(route('register.store'), [...$payload, 'phone' => '0612345678',
        'country_uuid' => Country::where('code', 'FR')->firstOrFail()->uuid])->assertSessionHasErrors('country_uuid');
    $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
});

test('phone audit is read only and does not alter administrative or verification state', function (): void {
    $user = User::factory()->create(['phone' => '0779107446', 'status' => StatusEnum::PENDING]);
    $attributes = $user->fresh()->getRawOriginal();
    expect(app(ContactMigrationAudit::class)->inspect())->toBe(['invalid' => [], 'duplicates' => [], 'changed' => 0]);
    expect($user->refresh()->getRawOriginal())->toBe($attributes);
});

test('migration preflight refuses invalid legacy phone data without altering it', function (): void {
    $user = User::factory()->create(['phone' => '0779107446']);
    DB::table('users')->where('id', $user->id)->update(['phone' => '+213123456789']);
    $before = DB::table('users')->where('id', $user->id)->first();
    $columns = $user->getConnection()->getSchemaBuilder()->getColumnListing('users');
    $migration = require base_path('database/migrations/central/2026_10_18_105836_add_international_phone_identity_to_users_table.php');
    expect(fn () => $migration->up())->toThrow(RuntimeException::class);
    expect(DB::table('users')->where('id', $user->id)->first())->toEqual($before);
    expect($user->getConnection()->getSchemaBuilder()->getColumnListing('users'))->toBe($columns);
});
