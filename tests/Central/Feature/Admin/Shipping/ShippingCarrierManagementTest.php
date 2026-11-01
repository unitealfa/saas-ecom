<?php

use App\Models\Central\Audit\Activity;
use App\Models\Central\Countries\Country;
use App\Models\Central\Geography\GeographicArea;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Roles\Role;
use App\Models\Central\Shipping\ShippingCarrier;
use App\Models\User;
use App\Services\Central\Shipping\ShippingCarrierService;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\CarrierPermissionSeeder;
use Database\Seeders\Central\CountrySeeder;
use Database\Seeders\Central\ShippingCarrierSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

function shippingAdmin(): User
{
    test()->seed(AuthorizationSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(Role::findByName('root', 'central'));

    return $admin;
}

function shippingStaff(string $permission): User
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('carrier-'.Str::random(10), [$permission => 7]));

    return $actor;
}

test('carrier seeding adds only Yalidine and preserves identities and administrative choices', function (): void {
    $this->seed([ShippingCarrierSeeder::class, CarrierPermissionSeeder::class]);
    $carrier = ShippingCarrier::sole();
    $uuid = $carrier->uuid;
    $carrier->forceFill(['name' => 'Nom personnalisé', 'is_active' => true])->save();
    $carrier->delete();
    $this->seed([ShippingCarrierSeeder::class, CarrierPermissionSeeder::class]);

    expect(ShippingCarrier::withTrashed()->count())->toBe(1);
    $carrier = ShippingCarrier::withTrashed()->sole();
    expect($carrier->uuid)->toBe($uuid)->and($carrier->trashed())->toBeTrue()
        ->and($carrier->name)->toBe('Nom personnalisé')
        ->and($carrier->default_api_url)->toBeNull();
    expect(Permission::where('name', 'like', 'saas.carriers.%')->count())->toBe(5);
});

test('all carrier HTTP actions refuse staff without their permission', function (): void {
    shippingAdmin();
    $carrier = ShippingCarrier::factory()->create();
    $actor = shippingStaff('saas.users.view');
    $this->actingAs($actor, 'central');
    foreach (['index', 'create', 'show', 'edit'] as $action) {
        $this->get(route('central.carriers.'.$action, in_array($action, ['show', 'edit'], true) ? $carrier : []))->assertForbidden();
    }
    $this->post(route('central.carriers.store'), [])->assertForbidden();
    $this->put(route('central.carriers.update', $carrier), [])->assertForbidden();
    $this->patch(route('central.carriers.activate', $carrier), [])->assertForbidden();
    $this->delete(route('central.carriers.archive', $carrier), [])->assertForbidden();
});

test('carrier permissions remain independent at the policy level', function (string $permission, string $ability): void {
    shippingAdmin();
    $carrier = ShippingCarrier::factory()->create();
    $actor = shippingStaff('saas.carriers.'.$permission);
    foreach (['viewAny', 'create', 'update', 'activate', 'archive'] as $candidate) {
        expect(Gate::forUser($actor)->allows($candidate,
            in_array($candidate, ['viewAny', 'create'], true) ? ShippingCarrier::class : $carrier))
            ->toBe($candidate === $ability);
    }
})->with(['view' => ['view', 'viewAny'], 'create' => ['create', 'create'],
    'update' => ['update', 'update'], 'activate' => ['activate', 'activate'], 'archive' => ['archive', 'archive']]);

test('carrier reader sees navigation list and details without mutation buttons', function (): void {
    shippingAdmin();
    $carrier = ShippingCarrier::factory()->create();
    $reader = shippingStaff('saas.carriers.view');
    $this->actingAs($reader, 'central')->get(route('central.carriers.index'))
        ->assertOk()->assertSee('Yalidine')->assertSee('Transporteurs')
        ->assertDontSee('Ajouter un transporteur')->assertDontSee('>Modifier<', false);
    $this->get(route('central.carriers.show', $carrier))->assertOk()
        ->assertSee('Non configurée')->assertDontSee('>Activer<', false)->assertDontSee('>Archiver<', false);
    $this->get(route('central.carriers.show', $carrier->id))->assertNotFound();
    $this->get(route('central.carriers.edit', $carrier))->assertForbidden();
});

test('carrier creation is inactive and rejects invalid codes duplicates or injected settings', function (): void {
    $admin = shippingAdmin();
    $this->actingAs($admin, 'central')->get(route('central.carriers.create'))->assertOk();
    $this->post(route('central.carriers.store'), ['code' => 'invalid code', 'name' => 'Inconnu'])->assertSessionHasErrors('code');
    $this->post(route('central.carriers.store'), ['code' => ShippingCarrier::YALIDINE, 'name' => 'Yalidine', 'is_active' => true])
        ->assertSessionHasErrors('is_active');
    $this->post(route('central.carriers.store'), ['code' => ShippingCarrier::YALIDINE, 'name' => 'Yalidine'])
        ->assertSessionHasNoErrors()->assertRedirect();
    $carrier = ShippingCarrier::sole();
    expect($carrier->is_active)->toBeFalse()->and($carrier->adapter)->toBe(ShippingCarrier::YALIDINE)
        ->and($carrier->default_api_url)->toBeNull();
    $this->post(route('central.carriers.store'), ['code' => ShippingCarrier::YALIDINE, 'name' => 'Doublon'])
        ->assertSessionHasErrors('code');
    expect(ShippingCarrier::count())->toBe(1);
    expect(Activity::forSubject($carrier)->where('event', 'carrier_created')->count())->toBe(1);
});

test('a custom carrier code is editable on creation without inheriting Yalidine configuration', function (): void {
    $admin = shippingAdmin();
    $this->actingAs($admin, 'central')->get(route('central.carriers.create'))->assertOk()
        ->assertSee('name="code"', false)->assertDontSee('readonly', false);
    $this->post(route('central.carriers.store'), ['code' => ' Fast-Delivery ', 'name' => 'Autre transporteur'])
        ->assertSessionHasNoErrors()->assertRedirect();
    $carrier = ShippingCarrier::sole();
    expect($carrier->code)->toBe('fast-delivery')->and($carrier->is_active)->toBeFalse()
        ->and($carrier->adapter)->toBe(ShippingCarrier::UNCONFIGURED_ADAPTER)
        ->and($carrier->reference_source)->toBe(ShippingCarrier::MANUAL_SOURCE)
        ->and($carrier->default_api_url)->toBeNull();
    $this->get(route('central.carriers.edit', $carrier))->assertOk()->assertSee('readonly', false);
    $this->put(route('central.carriers.update', $carrier), [
        'code' => 'changed-code', 'name' => 'Autre transporteur', 'expected_state' => $carrier->managementState(),
    ])->assertSessionHasErrors('code');
    $this->post(route('central.carriers.store'), ['code' => 'FAST-DELIVERY', 'name' => 'Doublon'])
        ->assertSessionHasErrors('code');
    expect($carrier->refresh()->code)->toBe('fast-delivery')->and(ShippingCarrier::count())->toBe(1);
});

test('carrier edits preserve identity activation and reject outdated forms', function (): void {
    $admin = shippingAdmin();
    $carrier = ShippingCarrier::factory()->create(['is_active' => true]);
    $uuid = $carrier->uuid;
    $original = $carrier->managementState();
    $this->actingAs($admin, 'central')->get(route('central.carriers.edit', $carrier))->assertOk();
    $this->put(route('central.carriers.update', $carrier), [
        'code' => $carrier->code, 'name' => 'Yalidine Express', 'expected_state' => $original,
    ])->assertSessionHasNoErrors()->assertRedirect();
    $this->put(route('central.carriers.update', $carrier), [
        'code' => $carrier->code, 'name' => 'Édition obsolète', 'expected_state' => $original,
    ])->assertSessionHasErrors('conflict');
    $carrier->refresh();
    expect($carrier->name)->toBe('Yalidine Express')->and($carrier->uuid)->toBe($uuid)
        ->and($carrier->is_active)->toBeTrue()->and($carrier->reference_version)->toBe(2);
    $this->patch(route('central.carriers.activate', $carrier), [
        'is_active' => false, 'expected_state' => $carrier->managementState(),
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($carrier->refresh()->is_active)->toBeFalse();
});

test('carrier writer without view can save and is redirected to the dashboard', function (): void {
    shippingAdmin();
    $carrier = ShippingCarrier::factory()->create();
    $writer = shippingStaff('saas.carriers.update');
    $this->actingAs($writer, 'central')->put(route('central.carriers.update', $carrier), [
        'code' => $carrier->code, 'name' => 'Nouveau nom', 'expected_state' => $carrier->managementState(),
    ])->assertRedirect(route('dashboard'))->assertSessionHasNoErrors();
});

test('carrier archival is logical and reserves its code without changing existing references', function (): void {
    $admin = shippingAdmin();
    $carrier = ShippingCarrier::factory()->create();
    $this->actingAs($admin, 'central')->delete(route('central.carriers.archive', $carrier), [
        'expected_state' => $carrier->managementState(),
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($carrier->fresh()->trashed())->toBeTrue();
    $this->get(route('central.carriers.show', $carrier))->assertOk()->assertSee('Archivé')->assertDontSee('>Modifier<', false);
    $this->get(route('central.carriers.index', ['archived' => true]))->assertOk()->assertSee('Yalidine');
    $this->post(route('central.carriers.store'), ['code' => $carrier->code, 'name' => 'Yalidine'])->assertSessionHasErrors('code');
    expect(ShippingCarrier::withTrashed()->count())->toBe(1);
});

test('a failed carrier transaction preserves state and audit history', function (): void {
    $admin = shippingAdmin();
    $carrier = ShippingCarrier::factory()->create();
    $before = Activity::count();
    try {
        $carrier->getConnection()->transaction(function () use ($admin, $carrier): void {
            app(ShippingCarrierService::class)->setActive($admin, $carrier, true, $carrier->managementState());
            throw new RuntimeException('Test rollback');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Test rollback');
    }
    expect($carrier->refresh()->is_active)->toBeFalse()->and(Activity::count())->toBe($before);
});

test('central geographic references prevent carrier archival', function (): void {
    $admin = shippingAdmin();
    $carrier = ShippingCarrier::factory()->create();
    $this->seed(CountrySeeder::class);
    $area = new GeographicArea;
    $area->forceFill([
        'country_id' => Country::where('code', 'DZ')->sole()->id,
        'type' => 1, 'code' => '16', 'name_fr' => 'Alger', 'is_active' => true,
        'reference_source' => 'test', 'reference_version' => 'test-1',
    ])->save();
    $carrier->getConnection()->table('carrier_geo_mappings')->insert([
        'uuid' => (string) Str::uuid(), 'carrier_id' => $carrier->id, 'geographic_area_id' => $area->id,
        'zone_type' => 1, 'external_code' => '16', 'external_name' => 'Test',
        'external_province_code' => '16', 'verification_source' => 'test', 'mapping_version' => 1, 'is_active' => false,
    ]);
    $this->actingAs($admin, 'central')->delete(route('central.carriers.archive', $carrier), [
        'expected_state' => $carrier->managementState(),
    ])->assertSessionHasErrors('carrier');
    expect($carrier->refresh()->trashed())->toBeFalse()->and($carrier->reference_version)->toBe(1);
});
