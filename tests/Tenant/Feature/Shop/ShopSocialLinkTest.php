<?php

use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Tenant\Users\StatusEnum;
use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Permissions\Permission;
use App\Models\Tenant\Shop\ShopAddress;
use App\Models\Tenant\Shop\ShopSocialLink;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Shop\ShopAddressService;
use App\Services\Tenant\Shop\ShopPublicUrl;
use App\Services\Tenant\Shop\ShopSocialLinkService;
use Database\Seeders\Tenant\ShopProfilePermissionSeeder;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

beforeEach(function (): void {
    $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
});

afterEach(function (): void {
    tenancy()->end();
    foreach (Tenant::withTrashed()->get() as $tenant) {
        TestCase::dropIsolatedMysqlDatabase($tenant->database()->getName());
    }
});

function socialEditor(): User
{
    app(ShopProfilePermissionSeeder::class)->run();
    $user = User::factory()->create([
        'status' => StatusEnum::ACTIVE,
        'membership_status' => MembershipStatusEnum::ACTIVE,
        'joined_at' => now(), 'email_verified_at' => now(),
    ]);
    foreach (['shop.social-links.create', 'shop.social-links.update', 'shop.social-links.archive', 'shop.addresses.create'] as $name) {
        $user->givePermissionTo(Permission::findByName($name, 'tenant'));
    }

    return $user;
}

test('public URL rejects credentials and secret tokens', function (): void {
    ShopPublicUrl::assertSafe('https://www.instagram.com/aydra/', 'url');
    expect(fn () => ShopPublicUrl::assertSafe('https://user:pass@example.com/', 'url'))
        ->toThrow(ValidationException::class);
    expect(fn () => ShopPublicUrl::assertSafe('https://example.com/page?api_key=private', 'url'))
        ->toThrow(ValidationException::class);
});

test('many links may share a network, and a linked address stays in the same tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $tenant->run(function (): void {
        $actor = socialEditor();
        app(ShopAddressService::class)->save($actor, [
            'label' => 'Showroom', 'address' => 'Alger', 'position' => 0,
            'visible' => true, 'is_primary' => true, 'opening_hours' => null,
        ]);
        $address = ShopAddress::query()->firstOrFail();
        $service = app(ShopSocialLinkService::class);
        $payload = ['label' => null, 'network' => 'Instagram', 'url' => 'https://instagram.com/one',
            'address_uuid' => $address->uuid, 'position' => 0, 'visible' => true];
        $service->save($actor, $payload);
        $payload['url'] = 'https://instagram.com/two';
        $service->save($actor, $payload);
        expect(ShopSocialLink::query()->count())->toBe(2)
            ->and(ShopAddress::query()->count())->toBe(1);
        $first = ShopSocialLink::query()->firstOrFail();
        $service->archive($actor, $first->uuid, $service->token($first));
        expect(ShopSocialLink::query()->count())->toBe(1)
            ->and(ShopSocialLink::withTrashed()->count())->toBe(2);
    });
});
