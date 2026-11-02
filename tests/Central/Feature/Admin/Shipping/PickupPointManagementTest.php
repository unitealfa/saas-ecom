<?php

use App\Enums\Central\Geography\GeoZoneTypeEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Geography\GeographicArea;
use App\Models\Central\Permissions\Permission;
use App\Models\Central\Roles\Role;
use App\Models\Central\Shipping\PickupPoint;
use App\Models\Central\Shipping\ShippingCarrier;
use App\Models\User;
use App\Services\Central\Shipping\PickupPointImportService;
use App\Services\Central\Shipping\PickupPointService;
use Database\Seeders\Central\AuthorizationSeeder;
use Database\Seeders\Central\CountrySeeder;
use Database\Seeders\Central\PickupPointPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed([CountrySeeder::class, AuthorizationSeeder::class]);
});

function pickupAdmin(): User
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::findByName('root', 'central'));

    return $actor;
}

function pickupStaff(string $permission): User
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::createWithPermissions('pickup-'.Str::random(12), [$permission => 7]));

    return $actor;
}

/** @return array<string, mixed> */
function pickupData(PickupPoint $point): array
{
    return ['carrier_uuid' => $point->carrier->uuid, 'province_uuid' => $point->province->uuid,
        'municipality_uuid' => $point->municipality?->uuid, 'external_code' => $point->external_code,
        ...$point->only(PickupPoint::EDITABLE)];
}

/** @param list<array<string, mixed>> $rows */
function pickupCsv(array $rows): UploadedFile
{
    $stream = fopen('php://temp', 'w+');
    fputcsv($stream, PickupPointImportService::HEADERS, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($stream, array_map(fn (string $key): string => (string) ($row[$key] ?? ''), PickupPointImportService::HEADERS), ',', '"', '');
    }
    rewind($stream);
    $contents = stream_get_contents($stream);
    fclose($stream);

    return UploadedFile::fake()->createWithContent('bureaux.csv', $contents);
}

test('pickup permission seeder preserves assignments and is idempotent', function (): void {
    $before = DB::table('model_has_permissions')->count();
    $this->seed(PickupPointPermissionSeeder::class);
    $ids = Permission::where('name', 'like', 'saas.pickup-points.%')->pluck('uuid', 'name')->all();
    $this->seed(PickupPointPermissionSeeder::class);
    expect($ids)->toHaveCount(6)
        ->and(Permission::where('name', 'like', 'saas.pickup-points.%')->pluck('uuid', 'name')->all())->toBe($ids)
        ->and(DB::table('model_has_permissions')->count())->toBe($before);
});

test('pickup permissions are independent including activation and import', function (): void {
    $point = PickupPoint::factory()->create();
    $subjects = ['viewAny' => PickupPoint::class, 'create' => PickupPoint::class, 'import' => PickupPoint::class,
        'update' => $point, 'activate' => $point, 'archive' => $point];
    foreach (['view' => 'viewAny', 'create' => 'create', 'update' => 'update', 'import' => 'import', 'activate' => 'activate', 'archive' => 'archive'] as $permission => $ability) {
        $actor = pickupStaff('saas.pickup-points.'.$permission);
        foreach ($subjects as $candidate => $subject) {
            expect(Gate::forUser($actor)->allows($candidate, $subject))->toBe($candidate === $ability);
        }
    }
});

test('every pickup endpoint denies staff without permission', function (): void {
    $point = PickupPoint::factory()->create();
    $this->actingAs(pickupStaff('saas.users.view'), 'central');
    foreach (['index', 'create', 'import'] as $action) {
        $this->get(route('central.pickup-points.'.$action))->assertForbidden();
    }
    foreach (['show', 'edit'] as $action) {
        $this->get(route('central.pickup-points.'.$action, $point))->assertForbidden();
    }
    $this->post(route('central.pickup-points.store'), [])->assertForbidden();
    $this->put(route('central.pickup-points.update', $point), [])->assertForbidden();
    $this->patch(route('central.pickup-points.activate', $point), [])->assertForbidden();
    $this->delete(route('central.pickup-points.archive', $point), [])->assertForbidden();
    $this->post(route('central.pickup-points.preview'), [])->assertForbidden();
    $this->post(route('central.pickup-points.confirm'), [])->assertForbidden();
});

test('pickup reader sees menu list and details without mutation buttons or numeric binding', function (): void {
    $point = PickupPoint::factory()->create();
    $this->actingAs(pickupStaff('saas.pickup-points.view'), 'central')
        ->get(route('central.pickup-points.index'))->assertOk()->assertSee('Bureaux de retrait')
        ->assertSee($point->name)->assertDontSee('Ajouter un bureau')->assertDontSee('>Activer<', false);
    $this->get(route('central.pickup-points.show', $point))->assertOk()->assertSee($point->address)
        ->assertDontSee('>Modifier<', false);
    $this->get(route('central.pickup-points.show', $point->id))->assertNotFound();
    $this->get(route('central.pickup-points.edit', $point))->assertForbidden();
});

test('pickup create form is writable and creates an inactive office with preserved external code', function (): void {
    $fixture = PickupPoint::factory()->create();
    $data = [...pickupData($fixture), 'external_code' => '0007', 'name' => 'Nouveau bureau'];
    $this->actingAs(pickupStaff('saas.pickup-points.create'), 'central')
        ->get(route('central.pickup-points.create'))->assertOk()->assertSee('name="external_code"', false)->assertDontSee('readonly', false);
    $this->post(route('central.pickup-points.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    $created = PickupPoint::where('external_code', '0007')->sole();
    expect($created->is_carrier_active)->toBeFalse()->and($created->reference_version)->toBe(1)
        ->and($created->carrier_id)->toBe($fixture->carrier_id);
    expect(Activity::forSubject($created)->where('event', 'pickup_point_created')->count())->toBe(1);
    $this->post(route('central.pickup-points.store'), [...$data, 'is_carrier_active' => true])->assertSessionHasErrors('is_carrier_active');
});

test('pickup codes are unique per carrier and remain reserved after archival', function (): void {
    $point = PickupPoint::factory()->create();
    $this->actingAs(pickupAdmin(), 'central');
    $this->post(route('central.pickup-points.store'), pickupData($point))->assertSessionHasErrors('external_code');
    $other = ShippingCarrier::factory()->create(['code' => 'another']);
    $this->post(route('central.pickup-points.store'), [...pickupData($point), 'carrier_uuid' => $other->uuid])->assertSessionHasNoErrors();
    $this->delete(route('central.pickup-points.archive', $point), ['expected_state' => $point->managementState()])->assertSessionHasNoErrors();
    $this->post(route('central.pickup-points.store'), pickupData($point))->assertSessionHasErrors('external_code');
    expect(PickupPoint::withTrashed()->count())->toBe(2)->and($point->refresh()->trashed())->toBeTrue();
    $this->get(route('central.pickup-points.show', $point))->assertOk()->assertSee('Archivé')->assertDontSee('>Activer<', false);
});

test('pickup zone validation rejects invalid type parent inactive zone and archived carrier', function (): void {
    $point = PickupPoint::factory()->create();
    $other = PickupPoint::factory()->create(['carrier_id' => $point->carrier_id]);
    $municipality = new GeographicArea;
    $municipality->forceFill(['country_id' => $point->province->country_id, 'parent_id' => $other->province_id,
        'type' => GeoZoneTypeEnum::MUNICIPALITY, 'code' => 'TEST-C', 'name_fr' => 'Commune test',
        'is_active' => true, 'reference_source' => 'test', 'reference_version' => 'test-1'])->save();
    $this->actingAs(pickupAdmin(), 'central');
    $data = [...pickupData($point), 'external_code' => 'new'];
    $this->post(route('central.pickup-points.store'), [...$data, 'province_uuid' => $municipality->uuid])->assertSessionHasErrors('province_uuid');
    $this->post(route('central.pickup-points.store'), [...$data, 'municipality_uuid' => $municipality->uuid])->assertSessionHasErrors('municipality_uuid');
    $this->post(route('central.pickup-points.store'), [...$data, 'province_uuid' => $other->province->uuid, 'municipality_uuid' => $municipality->uuid])->assertSessionHasNoErrors();
    $point->province->forceFill(['is_active' => false])->save();
    $this->post(route('central.pickup-points.store'), $data)->assertSessionHasErrors('province_uuid');
    $point->carrier->delete();
    $this->post(route('central.pickup-points.store'), $data)->assertSessionHasErrors('carrier_uuid');
});

test('pickup edit preserves identity and activation rejects stale forms and unsafe map links', function (): void {
    $point = PickupPoint::factory()->create(['is_carrier_active' => true]);
    $this->actingAs(pickupAdmin(), 'central')->get(route('central.pickup-points.edit', $point))->assertOk();
    $data = [...pickupData($point), 'expected_state' => $point->managementState()];
    $this->put(route('central.pickup-points.update', $point), [...$data, 'map_url' => 'javascript:alert(1)'])->assertSessionHasErrors('map_url');
    $this->put(route('central.pickup-points.update', $point), [...$data, 'external_code' => 'different'])->assertSessionHasErrors('conflict');
    $this->put(route('central.pickup-points.update', $point), [...$data, 'name' => 'Nom modifié'])->assertSessionHasNoErrors();
    $this->put(route('central.pickup-points.update', $point), [...$data, 'name' => 'Obsolète'])->assertSessionHasErrors('conflict');
    expect($point->refresh()->is_carrier_active)->toBeTrue()->and($point->reference_version)->toBe(2)->and($point->name)->toBe('Nom modifié');
});

test('pickup activation is separate and archive keeps identity with an atomic audit', function (): void {
    $point = PickupPoint::factory()->create();
    $this->actingAs(pickupStaff('saas.pickup-points.activate'), 'central')
        ->patch(route('central.pickup-points.activate', $point), ['expected_state' => $point->managementState(), 'is_carrier_active' => true])
        ->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    expect($point->refresh()->is_carrier_active)->toBeTrue();
    $this->actingAs(pickupStaff('saas.pickup-points.archive'), 'central')
        ->delete(route('central.pickup-points.archive', $point), ['expected_state' => $point->managementState()])
        ->assertSessionHasNoErrors();
    expect($point->refresh()->trashed())->toBeTrue()->and($point->is_carrier_active)->toBeFalse()
        ->and(Activity::forSubject($point)->where('event', 'pickup_point_archived')->count())->toBe(1);
});

test('pickup transaction rollback preserves office and audit', function (): void {
    $actor = pickupAdmin();
    $point = PickupPoint::factory()->create();
    $before = Activity::count();
    expect(fn () => DB::transaction(function () use ($actor, $point): void {
        app(PickupPointService::class)->setActive($actor, $point, true, $point->managementState());
        throw new RuntimeException('Rollback attendu');
    }))->toThrow(RuntimeException::class, 'Rollback attendu');
    expect($point->refresh()->is_carrier_active)->toBeFalse()->and(Activity::count())->toBe($before);
});

test('pickup import previews without writes confirms inactive offices and rejects replay', function (): void {
    $fixture = PickupPoint::factory()->create();
    $this->actingAs(pickupStaff('saas.pickup-points.import'), 'central');
    $data = [...pickupData($fixture), 'external_code' => 'IMPORT-1', 'name' => 'Bureau importé'];
    $this->post(route('central.pickup-points.preview'), ['carrier_uuid' => $fixture->carrier->uuid, 'file' => pickupCsv([$data])])
        ->assertRedirect(route('central.pickup-points.import'))->assertSessionHasNoErrors();
    expect(PickupPoint::count())->toBe(1);
    $preview = $this->get(route('central.pickup-points.import'))->assertOk()->assertSee('Bureau importé')->viewData('preview');
    $this->post(route('central.pickup-points.confirm'), ['token' => $preview['token']])->assertSessionHasNoErrors();
    $created = PickupPoint::where('external_code', 'IMPORT-1')->sole();
    expect($created->is_carrier_active)->toBeFalse()->and($created->synced_at)->not->toBeNull();
    $this->post(route('central.pickup-points.confirm'), ['token' => $preview['token']])->assertSessionHasErrors();
    expect(PickupPoint::count())->toBe(2);
});

test('pickup import preserves activation and rejects outdated previews atomically', function (): void {
    $actor = pickupAdmin();
    $point = PickupPoint::factory()->create(['is_carrier_active' => true]);
    $importer = app(PickupPointImportService::class);
    $rows = [[...pickupData($point), 'external_code' => 'NEW-FIRST'], [...pickupData($point), 'name' => 'Import à refuser']];
    $preview = $importer->preview($actor, $point->carrier->uuid, pickupCsv($rows));
    app(PickupPointService::class)->save($actor, [...pickupData($point), 'name' => 'Modifié entre-temps'], $point, $point->managementState());
    $before = Activity::count();
    expect(fn () => $importer->confirm($actor, $preview['token']))->toThrow(ValidationException::class);
    expect(PickupPoint::where('external_code', 'NEW-FIRST')->exists())->toBeFalse()->and(Activity::count())->toBe($before);
    $preview = $importer->preview($actor, $point->carrier->uuid, pickupCsv([[...pickupData($point->refresh()), 'name' => 'Import accepté']]));
    $importer->confirm($actor, $preview['token']);
    expect($point->refresh()->is_carrier_active)->toBeTrue()->and($point->name)->toBe('Import accepté');
});

test('pickup import checks duplicate codes archived rows wrong actor and expiration', function (): void {
    $actor = pickupAdmin();
    $point = PickupPoint::factory()->create();
    $importer = app(PickupPointImportService::class);
    $row = [...pickupData($point), 'external_code' => 'NEW'];
    expect(fn () => $importer->preview($actor, $point->carrier->uuid, pickupCsv([$row, $row])))->toThrow(ValidationException::class);
    $preview = $importer->preview($actor, $point->carrier->uuid, pickupCsv([$row]));
    expect(fn () => $importer->confirm(pickupStaff('saas.pickup-points.import'), $preview['token']))->toThrow(ValidationException::class);
    $this->travel(16)->minutes();
    expect(fn () => $importer->confirm($actor, $preview['token']))->toThrow(ValidationException::class);
    $this->travelBack();
    $point->delete();
    expect(fn () => $importer->preview($actor, $point->carrier->uuid, pickupCsv([pickupData($point)])))->toThrow(ValidationException::class);
});

test('pickup import errors return to a readable GET page and reject malformed csv', function (): void {
    $point = PickupPoint::factory()->create();
    $this->actingAs(pickupAdmin(), 'central')->followingRedirects()
        ->post(route('central.pickup-points.preview'), ['carrier_uuid' => $point->carrier->uuid,
            'file' => UploadedFile::fake()->createWithContent('broken.csv', "bad,headers\na,b\n")])
        ->assertOk()->assertSee('En-tête CSV invalide');
});
