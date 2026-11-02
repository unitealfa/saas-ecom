<?php

namespace App\Services\Central\Media;

use App\Enums\MediaVisibilityEnum;
use App\Models\Central\Media\Media;
use App\Models\Central\Users\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PrivateMediaAccessService
{
    public function download(User $actor, Media $media): StreamedResponse
    {
        abort_if(tenancy()->initialized, 403);
        $current = Media::whereKey($media->id)->firstOrFail();
        Gate::forUser($actor)->authorize('download', $current);
        abort_unless($this->hasSafeLocation($current), 404, 'Ce fichier n’est pas disponible.');

        $disk = Storage::disk(CentralMediaService::DISK);
        abort_unless($disk->exists($current->storage_key), 404, 'Ce fichier n’est pas disponible.');
        $handle = $disk->readStream($current->storage_key);
        abort_unless(is_resource($handle), 404, 'Ce fichier n’est pas disponible.');

        try {
            $hash = hash_init('sha256');
            hash_update_stream($hash, $handle);
            abort_unless(
                is_string($current->file_hash) && hash_equals($current->file_hash, hash_final($hash)),
                409, 'L’intégrité du fichier ne peut pas être confirmée.',
            );
            abort_unless(rewind($handle), 409, 'Le fichier ne peut pas être téléchargé.');
            activity()->causedBy($actor)->performedOn($current)->event('central_media_download_authorized')
                ->withProperties(['media_uuid' => $current->uuid])
                ->log('Téléchargement privé autorisé après contrôle du fichier.');
        } catch (Throwable $exception) {
            fclose($handle);
            throw $exception;
        }

        $name = Str::ascii($current->original_name);
        $name = preg_replace('/[\x00-\x1f\x7f\/\\\\]/', '_', $name) ?: 'fichier';

        return response()->streamDownload(static function () use ($handle): void {
            try {
                fpassthru($handle);
            } finally {
                fclose($handle);
            }
        }, $name, ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff']);
    }

    private function hasSafeLocation(Media $media): bool
    {
        if ($media->disk !== CentralMediaService::DISK || $media->visibility !== MediaVisibilityEnum::PRIVATE
            || str_contains($media->storage_key, '\\') || str_contains($media->storage_key, '..')
            || str_contains($media->storage_key, "\0")) {
            return false;
        }

        if (str_starts_with($media->storage_key, CentralMediaService::DIRECTORY.'/')) {
            return true;
        }

        // Les pièces de démonstration historiques gardent leur emplacement privé existant.
        if ($media->collection_name === 'local-fixtures' && $media->mime_type === 'application/pdf'
            && preg_match('/\Alocal-fixtures\/[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}\.pdf\z/D', $media->storage_key) === 1) {
            return true;
        }

        if ($media->model_type !== 'central_user' || $media->collection_name !== 'profile_photo') {
            return false;
        }

        $owner = User::withTrashed()->find($media->model_id);
        $extension = CentralMediaService::MIME_EXTENSIONS[$media->mime_type] ?? null;

        return $owner !== null && $extension !== null
            && $media->storage_key === 'profile-photos/'.$owner->uuid.'/'.$media->uuid.'.'.$extension;
    }
}
