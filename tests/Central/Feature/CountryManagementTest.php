<?php

use App\Models\Central\Country;
use App\Models\Central\Role;
use App\Models\User;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\CountrySeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([CountrySeeder::class, AuthorizationSeeder::class]);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::findByName('root', 'central'));
    Notification::fake();
});

test('countries have independent creation editing activation and removal permissions', function (): void {
    $editor = User::factory()->create();
    $editor->assignRole(Role::createWithPermissions('country-editor', ['saas.countries.update' => 7]));
    $country = Country::where('code', 'FR')->firstOrFail();
    $this->actingAs($editor, 'central')->get(route('central.countries.index'))->assertOk();
    $this->get(route('central.countries.edit', $country))->assertOk();
    $this->put(route('central.countries.update', $country), [
        'code' => 'FR', 'name_fr' => 'France modifiée', 'name_en' => 'France', 'name_ar' => 'فرنسا',
    ])->assertSessionHasNoErrors();
    expect($country->fresh()->name_fr)->toBe('France modifiée');
    $this->post(route('central.countries.store'), [
        'code' => 'TN', 'name_fr' => 'Tunisie', 'name_en' => 'Tunisia',
    ])->assertForbidden();
    $this->patch(route('central.countries.activate', $country), ['is_active' => true])->assertForbidden();
    $this->delete(route('central.countries.destroy', $country))->assertForbidden();
});

test('a new country starts inactive and active selection is enforced during registration', function (): void {
    $this->actingAs($this->admin, 'central')->post(route('central.countries.store'), [
        'code' => ' tn ', 'name_fr' => 'Tunisie', 'name_en' => 'Tunisia', 'name_ar' => 'تونس',
    ])->assertSessionHasNoErrors();
    $country = Country::where('code', 'TN')->firstOrFail();
    expect($country->is_active)->toBeFalse();
    $this->patch(route('central.countries.activate', $country), ['is_active' => true])->assertSessionHasNoErrors();
    expect($country->fresh()->is_active)->toBeTrue();
    $this->post(route('logout'))->assertRedirect();
    $this->get(route('register'))->assertOk()->assertSee($country->uuid);
    $this->post(route('register.store'), [
        'name' => 'Country Owner', 'email' => 'country-owner@example.test',
        'password' => 'password', 'password_confirmation' => 'password', 'country_uuid' => $country->uuid,
    ])->assertSessionHasNoErrors();
    expect(User::where('email', 'country-owner@example.test')->firstOrFail()->country_id)->toBe($country->id);
    Notification::assertSentTo(User::where('email', 'country-owner@example.test')->firstOrFail(), \Illuminate\Auth\Notifications\VerifyEmail::class);
});

test('inactive countries and archived codes cannot be selected or silently reused', function (): void {
    $france = Country::where('code', 'FR')->firstOrFail();
    $this->get(route('register'))->assertOk()->assertDontSee('value="'.$france->uuid.'"', escape: false);
    $this->post(route('register.store'), [
        'name' => 'Rejected Owner', 'email' => 'inactive@example.test',
        'password' => 'password', 'password_confirmation' => 'password', 'country_uuid' => $france->uuid,
    ])->assertSessionHasErrors('country_uuid');
    expect(User::where('email', 'inactive@example.test')->exists())->toBeFalse();
    $this->actingAs($this->admin, 'central')->delete(route('central.countries.destroy', $france))->assertSessionHasNoErrors();
    expect($france->fresh()->trashed())->toBeTrue();
    $this->post(route('central.countries.store'), [
        'code' => 'FR', 'name_fr' => 'France', 'name_en' => 'France',
    ])->assertSessionHasErrors('code');
});

test('countries referenced by accounts are retained and their code stays stable', function (): void {
    $country = Country::whereKey($this->admin->country_id)->firstOrFail();
    $this->actingAs($this->admin, 'central')->delete(route('central.countries.destroy', $country))
        ->assertSessionHasErrors('country');
    $this->put(route('central.countries.update', $country), [
        'code' => 'XX', 'name_fr' => 'Algérie', 'name_en' => 'Algeria',
    ])->assertSessionHasErrors('code');
    expect($country->fresh()->trashed())->toBeFalse();
    expect($country->fresh()->code)->toBe('DZ');
    $this->patch(route('central.countries.activate', $country), ['is_active' => false])->assertSessionHasNoErrors();
    expect($this->admin->fresh()->country_id)->toBe($country->id);
});

test('an expired country action no longer grants access to its module', function (): void {
    $editor = User::factory()->create();
    $role = Role::createWithPermissions('country-temporary', ['saas.countries.update' => 1]);
    $editor->assignRole($role);
    $editor->roles()->updateExistingPivot($role->id, ['assigned_at' => now()->subDays(2)]);
    $this->actingAs($editor, 'central')->get(route('central.countries.index'))->assertForbidden();
});
