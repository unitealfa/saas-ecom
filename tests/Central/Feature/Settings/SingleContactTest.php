<?php

use App\Models\Central\Roles\Role;
use App\Models\User;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\CountrySeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed([CountrySeeder::class, AuthorizationSeeder::class]);
    Notification::fake();
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::findByName('root', 'central'));
    $this->owner = User::factory()->create(['phone' => '0779107446']);
});

test('public and administrative creation reject the same existing normalized email and phone', function (): void {
    $public = ['last_name' => 'Nouveau', 'email' => 'new@example.test', 'phone' => '0779107446',
        'password' => 'password', 'password_confirmation' => 'password', 'country_uuid' => $this->owner->country->uuid];
    $this->postJson(route('register.store'), $public)->assertUnprocessable()->assertJsonValidationErrors('phone');
    $this->postJson(route('register.store'), [...$public, 'email' => strtoupper($this->owner->email), 'phone' => '0661123456'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
    $administrative = ['last_name' => 'Nouveau', 'email' => 'new@example.test', 'phone' => '+213779107446',
        'country_uuid' => $this->owner->country->uuid, 'account_kind' => 'merchant'];
    $this->actingAs($this->admin, 'central')->postJson(route('central.users.store'), $administrative)
        ->assertUnprocessable()->assertJsonValidationErrors('phone');
    $this->postJson(route('central.users.store'), [...$administrative, 'email' => $this->owner->email, 'phone' => '0661123456'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
    expect(User::count())->toBe(2);
});

test('profile and administrative edits keep their own contacts but reject another owners phone', function (): void {
    $this->actingAs($this->admin, 'central');
    Livewire::test('pages::settings.profile')->call('updateProfileInformation')->assertHasNoErrors();
    Livewire::test('pages::settings.profile')->set('phone', '0779107446')->call('updateProfileInformation')->assertHasErrors('phone');
    $this->putJson(route('central.users.update', $this->admin), [
        'last_name' => $this->admin->last_name, 'first_name' => $this->admin->first_name,
        'email' => $this->admin->email, 'phone' => '+213779107446', 'country_uuid' => $this->admin->country->uuid,
    ])->assertUnprocessable()->assertJsonValidationErrors('phone');
    expect($this->admin->fresh()->phone)->toBeNull()->and($this->owner->fresh()->phone)->toBe('+213779107446');
});

test('single contact accounts use users and enforce SQL uniqueness', function (): void {
    expect(Schema::hasTable('user_contacts'))->toBeFalse();
    expect(fn () => DB::table('users')->where('id', $this->admin->id)->update(['email' => $this->owner->email]))
        ->toThrow(UniqueConstraintViolationException::class);
    expect(fn () => DB::table('users')->where('id', $this->admin->id)->update([
        'phone' => $this->owner->phone, 'phone_country_id' => $this->owner->phone_country_id,
    ]))->toThrow(UniqueConstraintViolationException::class);
    expect(fn () => DB::table('users')->where('id', $this->admin->id)->update(['uuid' => $this->owner->uuid]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('replacing an email releases the old address and the provider only resolves the current address', function (): void {
    $previous = $this->owner->email;
    $this->owner->forceFill(['email' => 'replacement@example.test', 'email_verified_at' => null])->save();
    $provider = Auth::createUserProvider('central_users');
    expect($provider->retrieveByCredentials(['email' => $previous]))->toBeNull();
    expect($provider->retrieveByCredentials(['email' => 'replacement@example.test'])->id)->toBe($this->owner->id);
    expect($this->owner->fresh()->email_verified_at)->toBeNull();
});

test('obsolete multiple contact management is no longer accessible', function (): void {
    $this->actingAs($this->admin, 'central')->get('/settings/contacts')->assertNotFound();
    $this->post('/settings/contacts', ['kind' => 'email', 'value' => 'secondary@example.test'])->assertNotFound();
    $this->get('/admin/users/'.$this->owner->uuid.'/contacts')->assertNotFound();
});
