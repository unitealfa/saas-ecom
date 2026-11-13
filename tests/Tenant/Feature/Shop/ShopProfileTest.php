<?php

use App\Enums\Tenant\Users\MembershipStatusEnum;
use App\Enums\Tenant\Users\StatusEnum;
use App\Models\Central\Tenants\Tenant;
use App\Models\Tenant\Audit\Activity;
use App\Models\Tenant\Media\Media;
use App\Models\Tenant\Permissions\Permission;
use App\Models\Tenant\Shop\Shop;
use App\Models\Tenant\Users\User;
use App\Services\Tenant\Shop\ShopProfileCatalog;
use App\Services\Tenant\Shop\ShopProfileService;
use Database\Seeders\Tenant\ShopProfilePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

function profileEditor(): User
{
    app(ShopProfilePermissionSeeder::class)->run();
    $actor = User::factory()->create([
        'status' => StatusEnum::ACTIVE,
        'membership_status' => MembershipStatusEnum::ACTIVE,
        'joined_at' => now(), 'email_verified_at' => now(),
    ]);
    $actor->givePermissionTo(Permission::findByName('shop.profile.view', 'tenant'));
    $actor->givePermissionTo(Permission::findByName('shop.profile.update', 'tenant'));

    return $actor;
}

/** @return array<string, mixed> */
function profilePayload(Shop $shop): array
{
    return [
        'expected_state' => app(ShopProfileService::class)->token($shop),
        'description' => $shop->description, 'about' => $shop->about,
        'business_type' => $shop->business_type,
        'contact_email' => $shop->contact_email,
        'contact_phone' => $shop->contact_phone,
        'contact_whatsapp' => $shop->contact_whatsapp,
        'locale' => $shop->locale, 'timezone' => $shop->timezone,
        'theme_code' => $shop->theme_code, 'colors' => $shop->colors,
        'cart_lifetime_days' => $shop->cart_lifetime_days,
    ];
}

test('shop profile is isolated; name stays central and public cache changes only after commit', function (): void {
    $first = Tenant::factory()->create();
    $second = Tenant::factory()->create();
    $first->run(function (): void {
        $actor = profileEditor();
        $permissionIds = Permission::pluck('uuid', 'id')->all();
        app(ShopProfilePermissionSeeder::class)->run();
        expect(Permission::pluck('uuid', 'id')->all())->toBe($permissionIds)
            ->and(App\Models\Central\Permissions\Permission::where('name', 'like', 'shop.%')->exists())->toBeFalse();
        $shop = Shop::query()->sole();
        $catalog = app(ShopProfileCatalog::class);
        $before = $catalog->publicProfile();
        expect($before['description'])->toBe($shop->description);
        $payload = profilePayload($shop);
        $payload['description'] = 'Description publique A';
        $payload['contact_phone'] = '0550000000';
        app(ShopProfileService::class)->update($actor, $payload, null, null);
        expect(Activity::where('event', 'shop_profile_updated')->count())->toBe(1)
            ->and(App\Models\Central\Audit\Activity::where('event', 'shop_profile_updated')->count())->toBe(0);
        expect(Shop::query()->sole()->description)->toBe('Description publique A')
            ->and(Shop::query()->sole()->shop_name)->toBe($shop->shop_name)
            ->and($catalog->publicProfile()['description'])->toBe('Description publique A');
        $committedShop = Shop::sole();
        $rollbackData = profilePayload($committedShop);
        $rollbackData['description'] = 'Must roll back';
        expect(fn () => $committedShop->getConnection()->transaction(function () use ($actor, $rollbackData): void {
            app(ShopProfileService::class)->update($actor, $rollbackData, null, null);
            throw new RuntimeException('Rollback probe');
        }))->toThrow(RuntimeException::class, 'Rollback probe');
        expect(Shop::sole()->description)->toBe('Description publique A')
            ->and($catalog->publicProfile()['description'])->toBe('Description publique A')
            ->and(Activity::where('event', 'shop_profile_updated')->count())->toBe(1);
        expect(fn () => app(ShopProfileService::class)->update($actor, $payload, null, null))
            ->toThrow(ValidationException::class); // old form state
    });
    $second->run(function (): void {
        $profile = app(ShopProfileCatalog::class)->publicProfile();
        expect($profile['description'])->not->toBe('Description publique A');
    });
});

test('shop profile update rejects a user without local permission', function (): void {
    $tenant = Tenant::factory()->create();
    $tenant->run(function (): void {
        $actor = User::factory()->create([
            'status' => StatusEnum::ACTIVE, 'membership_status' => MembershipStatusEnum::ACTIVE,
            'joined_at' => now(), 'email_verified_at' => now(),
        ]);
        $shop = Shop::query()->sole();
        $data = profilePayload($shop);
        $data['about'] = 'Unauthorized';
        expect(fn () => app(ShopProfileService::class)->update($actor, $data, null, null))
            ->toThrow(AuthorizationException::class);
        expect($shop->fresh()->about)->not->toBe('Unauthorized');
    });
});

test('branding stores only accepted image bytes under tenant-local storage and media relation', function (): void {
    $tenant = Tenant::factory()->create();
    $tenant->run(function () use ($tenant): void {
        Storage::fake('local');
        $actor = profileEditor();
        $actor->givePermissionTo(Permission::findByName('shop.branding.update', 'tenant'));
        $shop = Shop::query()->sole();
        $file = UploadedFile::fake()->image('brand.png', 100, 100);
        app(ShopProfileService::class)->update($actor, profilePayload($shop), $file, null);
        $media = Media::query()->findOrFail(Shop::query()->sole()->logo_media_id);
        expect($media->model_type)->toBe($shop->getMorphClass())
            ->and($media->collection_name)->toBe('shop-logo')
            ->and($media->disk)->toBe('local')
            ->and($media->storage_key)->toContain($tenant->uuid);
        Storage::disk('local')->assertExists($media->storage_key);
    });
});
