<?php

use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Tenant\Users\StatusEnum;
use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Permissions\Permission;
use App\Models\Tenant\Shop\ShopAddress;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Shop\ShopAddressService;
use App\Services\Tenant\Shop\ShopOpeningHours;
use Database\Seeders\Tenant\ShopProfilePermissionSeeder;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/ShopProfileFixtures.php';

beforeEach(function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
});

afterEach(function (): void {
    tenancy()->end();
    foreach (Tenant::withTrashed()->get() as $tenant) {
        TestCase::dropIsolatedMysqlDatabase($tenant->database()->getName());
    }
});

function addressEditor(): User
{
    app(ShopProfilePermissionSeeder::class)->run();
    $user = User::factory()->create([
        'status' => StatusEnum::ACTIVE,
        'membership_status' => MembershipStatusEnum::ACTIVE,
        'joined_at' => now(), 'email_verified_at' => now(),
    ]);
    foreach (['create', 'update', 'archive'] as $name) {
        $user->givePermissionTo(Permission::findByName('shop.addresses.'.$name, 'tenant'));
    }

    return $user;
}

/** @return array<string, mixed> */
function addressPayload(string $label, bool $primary): array
{
    return [
        'label' => $label, 'address' => 'Rue des Tests, Alger', 'postal_code' => '16000',
        ...shopProfileGeography(), 'latitude' => null,
        'longitude' => null, 'map_url' => null, 'phone' => null,
        'opening_hours' => ['mon' => [['opens_at' => '22:00', 'closes_at' => '01:00', 'closes_next_day' => true]]],
        'position' => 0, 'visible' => true, 'is_primary' => $primary,
    ];
}

test('schedules validate cross-midnight and reject overlapping intervals, including Sunday', function (): void {
    expect(ShopOpeningHours::validate(['mon' => [['opens_at' => '22:00', 'closes_at' => '02:00', 'closes_next_day' => true]]]))->not->toBeNull();
    expect(fn () => ShopOpeningHours::validate([
        'sun' => [['opens_at' => '23:00', 'closes_at' => '02:00', 'closes_next_day' => true]],
        'mon' => [['opens_at' => '01:00', 'closes_at' => '09:00', 'closes_next_day' => false]],
    ]))->toThrow(ValidationException::class);
    expect(fn () => ShopOpeningHours::validate(['fri' => [['opens_at' => '18:60', 'closes_at' => '22:00', 'closes_next_day' => false]]]))->toThrow(ValidationException::class);
});

test('only one principal address is kept and archiving never deletes an older address', function (): void {
    $tenant = Tenant::factory()->create();
    $tenant->run(function (): void {
        $actor = addressEditor();
        $service = app(ShopAddressService::class);
        $service->save($actor, addressPayload('First', true));
        $first = ShopAddress::query()->firstOrFail();
        $service->save($actor, addressPayload('Second', true));
        expect(ShopAddress::query()->where('is_primary', true)->count())->toBe(1)
            ->and($first->fresh()->is_primary)->toBeFalse();
        $second = ShopAddress::query()->where('label', 'Second')->firstOrFail();
        $service->archive($actor, $second->uuid, $service->token($second));
        expect(ShopAddress::query()->count())->toBe(1)
            ->and(ShopAddress::withTrashed()->count())->toBe(2);
    });
});
