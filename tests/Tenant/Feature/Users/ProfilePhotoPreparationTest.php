<?php

use App\Models\Central\Media as CentralMedia;
use App\Models\Tenant;
use App\Models\Tenant\Media;
use App\Models\Tenant\User;
use App\Models\User as CentralUser;
use App\Services\Central\Users\ProfilePhotoService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Tenant\Tenancy\TenantTestSeeder;
use Tests\TestCase;

test('prepared tenant photos remain local to each database and cannot use central profile actions', function (): void {
    isolateLocalFixtureStorage();
    $tenants = collect();
    try {
        $this->artisan('migrate', ['--no-interaction' => true])->assertSuccessful();
        $this->seed([DatabaseSeeder::class, TenantTestSeeder::class]);
        $tenants = Tenant::orderBy('id')->get();
        $central = CentralUser::firstOrFail();
        $seen = [];
        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            $owner = User::firstOrFail();
            expect($owner->profile_photo_url)->toBe(asset('default-avatar.svg'));
            expect($owner->profilePhoto)->toBeNull();
            $photo = new Media;
            $uuid = Str::uuid()->toString();
            $photo->forceFill([
                'uuid' => $uuid, 'created_by_id' => $owner->id, 'model_id' => $owner->id,
                'model_type' => 'shop_user', 'collection_name' => 'profile_photo', 'disk' => 'local',
                'storage_key' => 'profile-photos/'.$owner->uuid.'/'.$uuid.'.png', 'mime_type' => 'image/png',
                'original_name' => 'portrait.png', 'size_bytes' => 100, 'width' => 16, 'height' => 16,
                'visibility' => 2, 'position' => 0, 'is_primary' => true,
            ])->save();
            expect($owner->fresh()->profilePhoto->uuid)->toBe($uuid);
            expect(Media::count())->toBe(1);
            expect($owner->fresh()->profile_photo_url)->toBe(asset('default-avatar.svg'));
            expect(Route::has('tenant.profile-photo.show'))->toBeFalse();
            expect($central->profile_photo_url)->toBe(asset('default-avatar.svg'));
            expect(fn () => app(ProfilePhotoService::class)->update($central, UploadedFile::fake()->image('portrait.png')))
                ->toThrow(AuthorizationException::class);
            $seen[$tenant->id] = ['owner_id' => $owner->id, 'media_id' => $photo->id, 'uuid' => $uuid];
            tenancy()->end();
        }
        expect($seen[$tenants[0]->id]['owner_id'])->toBe($seen[$tenants[1]->id]['owner_id']);
        expect($seen[$tenants[0]->id]['media_id'])->toBe($seen[$tenants[1]->id]['media_id']);
        expect($seen[$tenants[0]->id]['uuid'])->not->toBe($seen[$tenants[1]->id]['uuid']);
        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($tenant, $seen): void {
                expect(User::firstOrFail()->profilePhoto->uuid)->toBe($seen[$tenant->id]['uuid']);
                expect(Media::sole()->uuid)->toBe($seen[$tenant->id]['uuid']);
            });
        }
        expect(CentralMedia::count())->toBe(0);
    } finally {
        tenancy()->end();
        foreach ($tenants as $tenant) {
            TestCase::dropIsolatedMysqlDatabase($tenant->database()->getName());
        }
        cleanLocalFixtureStorage();
    }
});
