<?php

namespace App\Services\Central\Users;

use App\Enums\MediaVisibilityEnum;
use App\Models\Central\Media;
use App\Models\Central\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProfilePhotoService
{
    public const MAX_SIZE_KB = 2048;

    public const MAX_DIMENSION = 4096;

    public const MIME_EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private const string DISK = 'local';

    private const string COLLECTION = 'profile_photo';

    private const string DIRECTORY = 'profile-photos';

    private const string ABILITY_UPDATE = 'updateProfilePhoto';

    private const string ABILITY_VIEW = 'viewProfilePhoto';

    public function update(User $actor, UploadedFile $photo): void
    {
        Gate::forUser($actor)->authorize(self::ABILITY_UPDATE, $actor);

        [$mime, $width, $height] = $this->inspect($photo);

        $uuid = Str::uuid()->toString();
        $directory = $this->directory($actor);
        $filename = $uuid.'.'.self::MIME_EXTENSIONS[$mime];
        $key = $directory.'/'.$filename;
        $disk = $this->disk();

        if ($disk->putFileAs($directory, $photo, $filename) === false) {
            $this->deleteFile($disk, $key, $uuid);

            throw ValidationException::withMessages(['photo' => 'La photo n’a pas pu être enregistrée. Réessayez.']);
        }

        try {
            DB::transaction(function () use ($actor, $photo, $mime, $width, $height, $uuid, $key, $disk): void {
                $user = $this->lockUser($actor);

                Gate::forUser($actor)->authorize(self::ABILITY_UPDATE, $user);

                $previous = $user->profilePhoto;
                $previous?->delete();

                $media = $this->createMedia($user, $photo, $uuid, $key, $mime, $width, $height);

                activity()->causedBy($user)->performedOn($user)->event('profile_photo_updated')
                    ->withProperties(['media_uuid' => $media->uuid])
                    ->log('Photo de profil modifiée.');

                if ($previous !== null && $this->isProfilePhoto($previous, $user)) {
                    DB::afterCommit(fn () => $this->deleteFile($disk, $previous->storage_key, $previous->uuid));
                }
            });
        } catch (Throwable $exception) {
            $this->deleteFile($disk, $key, $uuid);

            throw $exception;
        }

        $actor->unsetRelation('profilePhoto');
    }

    public function remove(User $actor): void
    {
        $disk = $this->disk();

        DB::transaction(function () use ($actor, $disk): void {
            $user = $this->lockUser($actor);

            Gate::forUser($actor)->authorize(self::ABILITY_UPDATE, $user);

            $photo = $user->profilePhoto;

            if ($photo === null) {
                return;
            }

            $photo->delete();

            activity()->causedBy($user)->performedOn($user)->event('profile_photo_removed')
                ->withProperties(['media_uuid' => $photo->uuid])
                ->log('Photo de profil retirée.');

            if ($this->isProfilePhoto($photo, $user)) {
                DB::afterCommit(fn () => $this->deleteFile($disk, $photo->storage_key, $photo->uuid));
            }
        });

        $actor->unsetRelation('profilePhoto');
    }

    public function show(User $actor, User $user): BinaryFileResponse|StreamedResponse
    {
        Gate::forUser($actor)->authorize(self::ABILITY_VIEW, $user);

        $photo = $user->profilePhoto;
        $disk = $this->disk();
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];

        if ($photo === null || ! $this->isProfilePhoto($photo, $user) || ! $disk->exists($photo->storage_key)) {
            return response()->file(public_path('default-avatar.svg'), [...$headers, 'Content-Type' => 'image/svg+xml']);
        }

        return $disk->response(
            $photo->storage_key,
            'profile-photo.'.self::MIME_EXTENSIONS[$photo->mime_type],
            [...$headers, 'Content-Type' => $photo->mime_type],
        );
    }

    /** @return array{string, int, int} */
    private function inspect(UploadedFile $photo): array
    {
        $mime = $photo->getMimeType();

        if (! $photo->isValid()
            || ! isset(self::MIME_EXTENSIONS[$mime])
            || $photo->getSize() > self::MAX_SIZE_KB * 1024) {
            throw $this->invalidPhoto();
        }

        // Une image tronquée peut émettre un avertissement ; son échec devient l'erreur de validation ci-dessous.
        $dimensions = @getimagesize($photo->getRealPath());

        if ($dimensions === false
            || $dimensions[0] > self::MAX_DIMENSION
            || $dimensions[1] > self::MAX_DIMENSION) {
            throw $this->invalidPhoto();
        }

        return [$mime, $dimensions[0], $dimensions[1]];
    }

    private function invalidPhoto(): ValidationException
    {
        return ValidationException::withMessages([
            'photo' => 'Choisissez une image JPG, PNG ou WebP de 2 Mo maximum et de 4096 pixels maximum par côté.',
        ]);
    }

    private function lockUser(User $actor): User
    {
        return User::whereKey($actor->id)->where('uuid', $actor->uuid)->lockForUpdate()->firstOrFail();
    }

    private function createMedia(User $user, UploadedFile $photo, string $uuid, string $key, string $mime, int $width, int $height): Media
    {
        $media = new Media;

        $media->forceFill([
            'uuid' => $uuid,
            'created_by_id' => $user->id,
            'model_id' => $user->id,
            'model_type' => $user->getMorphClass(),
            'collection_name' => self::COLLECTION,
            'disk' => self::DISK,
            'storage_key' => $key,
            'mime_type' => $mime,
            'original_name' => Str::limit($photo->getClientOriginalName(), 255, ''),
            'size_bytes' => $photo->getSize(),
            'width' => $width,
            'height' => $height,
            'visibility' => MediaVisibilityEnum::PRIVATE,
            'position' => 0,
            'is_primary' => true,
            'file_hash' => hash_file('sha256', $photo->getRealPath()),
        ])->save();

        return $media;
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk(self::DISK);
    }

    private function directory(User $user): string
    {
        return self::DIRECTORY.'/'.$user->uuid;
    }

    private function isProfilePhoto(Media $photo, User $user): bool
    {
        $extension = self::MIME_EXTENSIONS[$photo->mime_type] ?? null;

        return $extension !== null
            && $photo->disk === self::DISK
            && $photo->visibility === MediaVisibilityEnum::PRIVATE
            && $photo->model_type === $user->getMorphClass()
            && $photo->model_id === $user->id
            && $photo->collection_name === self::COLLECTION
            && $photo->storage_key === $this->directory($user).'/'.$photo->uuid.'.'.$extension;
    }

    private function deleteFile(FilesystemAdapter $disk, string $key, string $uuid): void
    {
        try {
            if ($disk->exists($key) && ! $disk->delete($key)) {
                Log::warning('Profile photo file cleanup failed.', ['media_uuid' => $uuid]);
            }
        } catch (Throwable $exception) {
            Log::warning('Profile photo file cleanup failed.', [
                'media_uuid' => $uuid,
                'exception_class' => $exception::class,
            ]);
        }
    }
}
