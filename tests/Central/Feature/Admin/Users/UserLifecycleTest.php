<?php

use App\Enums\Central\Users\StatusEnum;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Role;
use App\Models\Central\Tenant;
use App\Models\User;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Stancl\Tenancy\Events\TenantCreated;

beforeEach(function (): void {
    $this->seed(AuthorizationSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::findByName('root', 'central'));
    Notification::fake();
    Queue::fake();
});

test('an editor without the read permission can list inspect and edit users until its action expires', function (): void {
    $editor = User::factory()->create();
    $role = Role::createWithPermissions('edit-only', ['saas.users.update' => 1]);
    $editor->assignRole($role);
    $merchant = User::factory()->create();
    $this->actingAs($editor, 'central')->get(route('central.users.index'))->assertOk()
        ->assertSee(route('central.users.edit', $merchant), escape: false);
    $this->get(route('central.users.show', $merchant))->assertOk();
    $this->get(route('central.users.edit', $merchant))->assertOk();
    $this->delete(route('central.users.destroy', $merchant))->assertForbidden();
    $editor->roles()->updateExistingPivot($role->id, ['assigned_at' => now()->subDays(2)]);
    $this->get(route('central.users.index'))->assertForbidden();
});

test('user management actions have a global scope without target lists', function (): void {
    $editor = User::factory()->create();
    $editor->assignRole(Role::createWithPermissions('global-editor', ['saas.users.update' => 9]));
    $first = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($editor, 'central')->get(route('central.users.index'))->assertOk()
        ->assertSee($first->email)->assertSee($other->email);
    $this->get(route('central.users.show', $first))->assertOk();
    $this->get(route('central.users.show', $other))->assertOk();
});
test('suspension invalidates sessions and reactivation requires its own permission', function (): void {
    $moderator = User::factory()->create();
    $moderator->assignRole(Role::createWithPermissions('suspend-only', ['saas.users.suspend' => 9999]));
    $target = User::factory()->create();
    DB::table('sessions')->insert([
        'id' => 'target-session', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time(),
    ]);
    $this->actingAs($moderator, 'central')->patch(route('central.users.status', $target), ['status' => StatusEnum::SUSPENDED->value])
        ->assertSessionHasNoErrors();
    expect($target->fresh()->status)->toBe(StatusEnum::SUSPENDED);
    expect(DB::table('sessions')->where('id', 'target-session')->exists())->toBeFalse();
    $this->patch(route('central.users.status', $target), ['status' => StatusEnum::ACTIVE->value])->assertForbidden();
    $this->actingAs($target->fresh(), 'central')->get(route('dashboard'))->assertForbidden();
    $this->actingAs($this->admin, 'central')->patch(route('central.users.status', $target), ['status' => StatusEnum::ACTIVE->value])
        ->assertSessionHasNoErrors();
    expect($target->fresh()->status)->toBe(StatusEnum::ACTIVE);
});

test('no admin can suspend delete or reactivate the super admin through these actions', function (): void {
    $this->actingAs($this->admin, 'central');
    foreach ([StatusEnum::SUSPENDED, StatusEnum::ACTIVE] as $status) {
        $this->patch(route('central.users.status', $this->admin), ['status' => $status->value])->assertForbidden();
    }
    $this->delete(route('central.users.destroy', $this->admin))->assertForbidden();
    expect($this->admin->fresh()->trashed())->toBeFalse();
    expect($this->admin->fresh()->status)->toBe(StatusEnum::ACTIVE);
});

test('logical account deletion retains its shop records', function (): void {
    $target = User::factory()->create();
    Event::fake([TenantCreated::class]);
    $shop = Tenant::factory()->create(['user_id' => $target->id]);
    $this->actingAs($this->admin, 'central')->delete(route('central.users.destroy', $target))->assertSessionHasNoErrors();
    $deleted = User::withTrashed()->findOrFail($target->id);
    expect($deleted->trashed())->toBeTrue();
    expect($deleted->status)->toBe(StatusEnum::DELETED);
    expect(Tenant::findOrFail($shop->id)->user_id)->toBe($target->id);
});

test('all editable professional fields are saved without changing secrets or verification proofs', function (): void {
    $target = User::factory()->create();
    $password = $target->password;
    $fields = [
        'legal_form' => 'Entreprise individuelle', 'activity_nature' => 'Commerce',
        'nif' => '001234', 'nis' => '009876', 'registration_number' => 'RC123',
        'artisan_card_number' => 'ART123', 'legal_address' => 'Alger', 'share_capital' => '1200.50',
    ];
    $this->actingAs($this->admin, 'central')->put(route('central.users.update', $target), [
        'last_name' => $target->last_name, 'first_name' => $target->first_name,
        'email' => $target->email, 'country_uuid' => $target->country->uuid, 'locale' => 'ar', ...$fields,
    ])->assertSessionHasNoErrors();
    $updated = $target->fresh();
    foreach ($fields as $field => $value) {
        expect($updated->getAttribute($field))->toBe($value);
    }
    expect($updated->getAttribute('locale'))->toBe('ar');
    expect($updated->legal_profile_version)->toBe(1);
    expect($updated->legal_verification_status)->toBe(VerificationStatusEnum::PENDING);
    expect($updated->password)->toBe($password);
    $this->get(route('central.users.show', $updated))->assertOk()->assertSee('Abonnements')
        ->assertSee('RC123')->assertDontSee($password, escape: false)->assertDontSee('Identifiant public');
    $this->put(route('central.users.update', $target), [
        'last_name' => $target->last_name, 'email' => $target->email,
        'country_uuid' => $target->country->uuid, 'legal_verified_at' => now()->toDateTimeString(),
    ])->assertSessionHasErrors('legal_verified_at');
});
