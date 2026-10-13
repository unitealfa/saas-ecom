<?php

namespace App\Services\Central\Users;

use App\Models\Central\Media;
use App\Models\Central\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
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

    public function update(User $actor, UploadedFile $photo): void
    {
        Gate::forUser($actor)->authorize('updateProfilePhoto', $actor);
        $mime = $photo->getMimeType();
        $dimensions = $photo->isValid() && isset(self::MIME_EXTENSIONS[$mime])
            && $photo->getSize() <= self::MAX_SIZE_KB * 1024 ? getimagesize($photo->getRealPath()) : false;
        if (! $photo->isValid() || ! isset(self::MIME_EXTENSIONS[$mime]) || $dimensions === false
            || $photo->getSize() > self::MAX_SIZE_KB * 1024
            || $dimensions[0] > self::MAX_DIMENSION || $dimensions[1] > self::MAX_DIMENSION) {
            throw ValidationException::withMessages(['photo' => 'Choisissez une image JPG, PNG ou WebP de 2 Mo maximum et de 4096 pixels maximum par côté.']);
        }
        $uuid = Str::uuid()->toString();
        $filename = $uuid.'.'.self::MIME_EXTENSIONS[$mime];
        $directory = 'profile-photos/'.$actor->uuid;
        $key = $directory.'/'.$filename;
        $disk = Storage::disk('local');
        if ($disk->putFileAs($directory, $photo, $filename) === false) {
            $this->deleteFile($disk, $key, $uuid);
            throw ValidationException::withMessages(['photo' => 'La photo n’a pas pu être enregistrée. Réessayez.']);
        }
        try {
            $actor->getConnection()->transaction(function () use ($actor, $photo, $mime, $dimensions, $uuid, $key, $disk): void {
                $user = User::whereKey($actor->id)->where('uuid', $actor->uuid)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('updateProfilePhoto', $user);
                $previous = $user->profilePhoto;
                $previous?->delete();
                $media = new Media;
                $media->forceFill([
                    'uuid' => $uuid, 'created_by_id' => $user->id, 'model_id' => $user->id,
                    'model_type' => $user->getMorphClass(), 'collection_name' => 'profile_photo',
                    'disk' => 'local', 'storage_key' => $key, 'mime_type' => $mime,
                    'original_name' => Str::limit($photo->getClientOriginalName(), 255, ''),
                    'size_bytes' => $photo->getSize(), 'width' => $dimensions[0], 'height' => $dimensions[1],
                    'visibility' => 2, 'position' => 0, 'is_primary' => true,
                    'file_hash' => hash_file('sha256', $photo->getRealPath()),
                ])->save();
                activity()->causedBy($user)->performedOn($user)->event('profile_photo_updated')
                    ->withProperties(['media_uuid' => $media->uuid])->log('Photo de profil modifiée.');
                if ($previous !== null && $this->isProfilePhoto($previous, $user)) {
                    $user->getConnection()->afterCommit(fn () => $this->deleteFile($disk, $previous->storage_key, $previous->uuid));
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
        $disk = Storage::disk('local');
        $actor->getConnection()->transaction(function () use ($actor, $disk): void {
            $user = User::whereKey($actor->id)->where('uuid', $actor->uuid)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('updateProfilePhoto', $user);
            $photo = $user->profilePhoto;
            if ($photo === null) {
                return;
            }
            $photo->delete();
            activity()->causedBy($user)->performedOn($user)->event('profile_photo_removed')
                ->withProperties(['media_uuid' => $photo->uuid])->log('Photo de profil retirée.');
            if ($this->isProfilePhoto($photo, $user)) {
                $user->getConnection()->afterCommit(fn () => $this->deleteFile($disk, $photo->storage_key, $photo->uuid));
            }
        });
        $actor->unsetRelation('profilePhoto');
    }

    public function show(User $actor, User $user): BinaryFileResponse|StreamedResponse
    {
        Gate::forUser($actor)->authorize('viewProfilePhoto', $user);
        $photo = $user->profilePhoto;
        $headers = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        $disk = Storage::disk('local');
        if ($photo === null || ! $this->isProfilePhoto($photo, $user) || ! $disk->exists($photo->storage_key)) {
            return response()->file(public_path('default-avatar.svg'), [...$headers, 'Content-Type' => 'image/svg+xml']);
        }

        return $disk->response($photo->storage_key, 'profile-photo.'.self::MIME_EXTENSIONS[$photo->mime_type],
            [...$headers, 'Content-Type' => $photo->mime_type]);
    }

    private function isProfilePhoto(Media $photo, User $user): bool
    {
        $extension = self::MIME_EXTENSIONS[$photo->mime_type] ?? null;

        return $extension !== null && $photo->disk === 'local' && $photo->visibility === 2
            && $photo->model_type === $user->getMorphClass() && $photo->model_id === $user->id
            && $photo->collection_name === 'profile_photo'
            && $photo->storage_key === 'profile-photos/'.$user->uuid.'/'.$photo->uuid.'.'.$extension;
    }

    private function deleteFile(FilesystemAdapter $disk, string $key, string $uuid): void
    {
        try {
            if ($disk->exists($key) && ! $disk->delete($key)) {
                Log::warning('Profile photo file cleanup failed.', ['media_uuid' => $uuid]);
            }
        } catch (Throwable $exception) {
            Log::warning('Profile photo file cleanup failed.', ['media_uuid' => $uuid, 'exception_class' => $exception::class]);
        }
    }
}
