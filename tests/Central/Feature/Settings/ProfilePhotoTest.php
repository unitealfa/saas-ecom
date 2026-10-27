<?php

use App\Enums\Central\Users\StatusEnum;
use App\Enums\MediaVisibilityEnum;
use App\Models\Central\Audit\Activity;
use App\Models\Central\Media\Media;
use App\Models\Central\Roles\Role;
use App\Models\User;
use App\Services\Central\Users\ProfilePhotoService;
use Database\Seeders\Central\AuthorizationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Storage::fake('local');
});

test('existing and new central accounts share the default avatar without creating media rows', function (): void {
    $this->seed(AuthorizationSeeder::class);
    $users = User::factory()->count(3)->create();
    $users[1]->assignRole(Role::findByName('user-reader', 'central'));
    $users[2]->assignRole(Role::findByName('root', 'central'));
    foreach ($users as $user) {
        expect($user->profile_photo_url)->toBe(asset('default-avatar.svg'));
        $this->actingAs($user, 'central')->get(route('profile.edit'))->assertOk()
            ->assertSee('default-avatar.svg')->assertSee('Enregistrer la photo');
    }
    expect(Media::count())->toBe(0);
});

test('an account can upload a private photo with its real parent and read it securely', function (string $extension, string $mime): void {
    User::factory()->create();
    $user = User::factory()->create();
    $before = $user->fresh()->getAttributes();
    $image = UploadedFile::fake()->image('portrait.'.$extension, 32, 32);
    $contents = file_get_contents($image->getRealPath());

    $this->actingAs($user, 'central')->post(route('central.profile-photo.update'), ['photo' => $image])
        ->assertSessionHasNoErrors()->assertRedirect(route('profile.edit'));
    $photo = $user->fresh()->profilePhoto;
    expect($photo)->not->toBeNull();
    expect($photo->model_type)->toBe('central_user');
    expect($photo->model_id)->toBe($user->id);
    expect($photo->model_id)->not->toBe($photo->id);
    expect($photo->created_by_id)->toBe($user->id);
    expect($photo->mime_type)->toBe($mime);
    expect($photo->visibility)->toBe(MediaVisibilityEnum::PRIVATE);
    expect($photo->is_primary)->toBeTrue();
    expect($photo->file_hash)->toBe(hash('sha256', $contents));
    expect($user->fresh()->getAttributes())->toBe($before);
    Storage::disk('local')->assertExists($photo->storage_key);
    expect(Activity::forSubject($user)->where('event', 'profile_photo_updated')->count())->toBe(1);
    $this->get(route('profile.edit'))->assertOk()->assertSee($user->fresh()->profile_photo_url)->assertSee('Retirer la photo');
    $response = $this->get($user->fresh()->profile_photo_url)->assertOk()->assertHeader('Content-Type', $mime)
        ->assertHeader('X-Content-Type-Options', 'nosniff')->assertStreamedContent($contents);
    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
})->with([['jpg', 'image/jpeg'], ['png', 'image/png'], ['webp', 'image/webp']]);

test('replacing and removing a photo retains at most one active media and restores the default', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user, 'central')->post(route('central.profile-photo.update'), ['photo' => UploadedFile::fake()->image('first.png')])
        ->assertSessionHasNoErrors();
    $first = $user->fresh()->profilePhoto;
    $firstUrl = $user->fresh()->profile_photo_url;
    $this->post(route('central.profile-photo.update'), ['photo' => UploadedFile::fake()->image('second.jpg')])
        ->assertSessionHasNoErrors();
    $second = $user->fresh()->profilePhoto;
    expect($second->uuid)->not->toBe($first->uuid);
    expect($user->fresh()->profile_photo_url)->not->toBe($firstUrl);
    expect(Media::count())->toBe(1);
    expect(Media::withTrashed()->findOrFail($first->id)->trashed())->toBeTrue();
    Storage::disk('local')->assertMissing($first->storage_key)->assertExists($second->storage_key);

    $this->delete(route('central.profile-photo.destroy'))->assertRedirect(route('profile.edit'));
    expect($user->fresh()->profile_photo_url)->toBe(asset('default-avatar.svg'));
    expect(Media::count())->toBe(0);
    Storage::disk('local')->assertMissing($second->storage_key);
    $this->delete(route('central.profile-photo.destroy'))->assertSessionHasNoErrors();
    expect(Activity::forSubject($user)->where('event', 'profile_photo_removed')->count())->toBe(1);
});

test('invalid profile photo uploads cannot create files or media', function (string $invalid): void {
    $user = User::factory()->create();
    $file = match ($invalid) {
        'pdf' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
        'svg' => UploadedFile::fake()->create('image.svg', 10, 'image/svg+xml'),
        'fake_png' => UploadedFile::fake()->create('image.png', 10, 'image/png'),
        'large' => UploadedFile::fake()->image('image.png')->size(2049),
        'wide' => UploadedFile::fake()->image('image.png', 4097, 1),
    };
    $this->actingAs($user, 'central')->post(route('central.profile-photo.update'), ['photo' => $file])
        ->assertSessionHasErrors('photo');
    expect(Media::count())->toBe(0);
    expect(Storage::disk('local')->allFiles('profile-photos'))->toBe([]);
})->with(['pdf', 'svg', 'fake_png', 'large', 'wide']);

test('photo access uses account authorization and upload cannot select another owner', function (): void {
    $this->seed(AuthorizationSeeder::class);
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $file = UploadedFile::fake()->image('portrait.png');
    $this->post(route('central.profile-photo.update'), ['photo' => $file])->assertRedirect(route('login'));
    $this->actingAs($owner, 'central')->post(route('central.profile-photo.update'), [
        'photo' => $file, 'user_id' => $other->id, 'model_id' => $other->id, 'disk' => 'public',
    ])->assertSessionHasNoErrors();
    expect(Media::sole()->model_id)->toBe($owner->id);
    expect($other->fresh()->profilePhoto)->toBeNull();
    $url = $owner->fresh()->profile_photo_url;
    $this->actingAs($other, 'central')->get($url)->assertForbidden();
    $other->assignRole(Role::findByName('user-reader', 'central'));
    $this->get($url)->assertOk();
});

test('a stale suspended account cannot mutate its photo through HTTP or the service', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user, 'central')->get(route('profile.edit'))->assertOk();
    User::whereKey($user->id)->update(['status' => StatusEnum::SUSPENDED->value]);
    $this->post(route('central.profile-photo.update'), ['photo' => UploadedFile::fake()->image('portrait.png')])->assertForbidden();
    expect(fn () => app(ProfilePhotoService::class)->update($user, UploadedFile::fake()->image('portrait.png')))
        ->toThrow(AuthorizationException::class);
    expect(Media::count())->toBe(0);
    expect(Storage::disk('local')->allFiles('profile-photos'))->toBe([]);
});

test('a failed database mutation keeps the previous photo and removes the newly stored file', function (): void {
    $user = User::factory()->create();
    app(ProfilePhotoService::class)->update($user, UploadedFile::fake()->image('first.png'));
    $original = $user->fresh()->profilePhoto;
    $dispatcher = Media::getEventDispatcher();
    Media::setEventDispatcher(clone $dispatcher);
    try {
        Media::creating(fn () => throw new RuntimeException('Simulated storage metadata failure.'));
        expect(fn () => app(ProfilePhotoService::class)->update($user, UploadedFile::fake()->image('second.png')))
            ->toThrow(RuntimeException::class);
    } finally {
        Media::setEventDispatcher($dispatcher);
    }
    expect($user->fresh()->profilePhoto->uuid)->toBe($original->uuid);
    expect(Media::count())->toBe(1);
    expect(Storage::disk('local')->allFiles('profile-photos'))->toBe([$original->storage_key]);
    expect(Activity::forSubject($user)->where('event', 'profile_photo_updated')->count())->toBe(1);
});

test('missing photo files fall back to the common avatar without exposing another private path', function (): void {
    $user = User::factory()->create();
    app(ProfilePhotoService::class)->update($user, UploadedFile::fake()->image('portrait.png'));
    $photo = $user->fresh()->profilePhoto;
    Storage::disk('local')->delete($photo->storage_key);
    $this->actingAs($user, 'central')->get($user->fresh()->profile_photo_url)->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    $photo->forceFill(['storage_key' => '../private-file'])->save();
    $this->get($user->fresh()->profile_photo_url)->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
});

test('a disk write failure is reported without creating media or a success message', function (): void {
    $user = User::factory()->create();
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('putFileAs')->once()->andReturn(false);
    $disk->shouldReceive('exists')->once()->andReturn(false);
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);
    $this->actingAs($user, 'central')->post(route('central.profile-photo.update'), ['photo' => UploadedFile::fake()->image('portrait.png')])
        ->assertSessionHasErrors('photo')->assertSessionMissing('photo_status');
    expect(Media::count())->toBe(0);
});

test('the photo service also rejects non-image input before attempting storage', function (): void {
    $user = User::factory()->create();
    expect(fn () => app(ProfilePhotoService::class)->update($user, UploadedFile::fake()->create('document.pdf', 10, 'application/pdf')))
        ->toThrow(ValidationException::class);
    expect(Media::count())->toBe(0);
    expect(Storage::disk('local')->allFiles('profile-photos'))->toBe([]);
});

test('service audit rejects a truncated JPEG with a validation error before storage', function (): void {
    $user = User::factory()->create();
    $photo = UploadedFile::fake()->createWithContent('truncated.jpg', "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00");

    expect($photo->getMimeType())->toBe('image/jpeg');
    expect(fn () => app(ProfilePhotoService::class)->update($user, $photo))->toThrow(ValidationException::class);
    expect(Media::count())->toBe(0);
});

test('private photo URLs reject a missing or modified signature', function (): void {
    $user = User::factory()->create();
    app(ProfilePhotoService::class)->update($user, UploadedFile::fake()->image('portrait.png'));
    $signed = $user->fresh()->profile_photo_url;
    $this->actingAs($user, 'central')->get($signed)->assertOk();
    $this->get(route('central.profile-photo.show', $user))->assertForbidden();
    $this->get($signed.'&v=changed')->assertForbidden();
});
