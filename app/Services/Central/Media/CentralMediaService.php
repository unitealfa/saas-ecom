<?php

namespace App\Services\Central\Media;

use App\Enums\MediaVisibilityEnum;
use App\Models\Central\Media\Media;
use App\Models\Central\Users\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CentralMediaService
{
    public const int MAX_SIZE_KB = 10240;

    public const int MAX_DIMENSION = 4096;

    public const array MIME_EXTENSIONS = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public const string DISK = 'local';

    public const string DIRECTORY = 'central/private';

    private const int PER_PAGE = 20;

    private const int ATTEMPTS = 3;

    private const array REFERENCES = [
        'saas_invoices' => ['document_media_id'],
        'saas_document_deliveries' => ['proof_media_id'],
        'saas_transfers' => ['proof_media_id', 'source_proof_media_id'],
    ];

    /** @return array<string, mixed> */
    public static function uploadRules(): array
    {
        return ['file' => ['required', 'file', 'max:'.self::MAX_SIZE_KB,
            'mimetypes:'.implode(',', array_keys(self::MIME_EXTENSIONS)),
            'extensions:pdf,jpg,jpeg,png,webp']];
    }

    /** @param array<string, mixed> $filters
     * @return LengthAwarePaginator<int, Media>
     */
    public function search(User $actor, array $filters): LengthAwarePaginator
    {
        $this->authorize($actor, 'viewAny');
        $search = trim((string) ($filters['search'] ?? ''));
        $pattern = '%'.addcslashes($search, '\\%_').'%';

        return Media::with('creator')
            ->when($filters['archived'] ?? false, fn (Builder $query) => $query->onlyTrashed())
            ->when($search !== '', fn (Builder $query) => $query->where('original_name', 'like', $pattern))
            ->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();
    }

    public function upload(User $actor, UploadedFile $file): Media
    {
        $this->authorize($actor, 'upload');
        Validator::make(['file' => $file], self::uploadRules())->validate();
        $mime = $file->getMimeType();
        if ($mime === null || ! isset(self::MIME_EXTENSIONS[$mime])) {
            $this->fail('Le type réel du fichier n’est pas autorisé.');
        }
        $extensions = $mime === 'image/jpeg' ? ['jpg', 'jpeg'] : [self::MIME_EXTENSIONS[$mime]];
        if (! in_array(strtolower($file->getClientOriginalExtension()), $extensions, true)) {
            $this->fail('L’extension du fichier ne correspond pas à son contenu réel.');
        }
        [$width, $height] = $this->dimensions($file, $mime);
        $hash = hash_file('sha256', $file->getPathname());
        if ($hash === false) {
            $this->fail('Le contenu du fichier ne peut pas être contrôlé.');
        }

        $uuid = (string) Str::uuid();
        $filename = $uuid.'.'.self::MIME_EXTENSIONS[$mime];
        $key = self::DIRECTORY.'/'.$filename;
        $disk = Storage::disk(self::DISK);

        try {
            if ($disk->putFileAs(self::DIRECTORY, $file, $filename, ['visibility' => 'private']) === false) {
                $this->fail('Le fichier n’a pas pu être enregistré. Réessayez.');
            }

            return DB::transaction(function () use ($actor, $file, $mime, $width, $height, $hash, $uuid, $key): Media {
                $owner = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $this->authorize($actor, 'upload');
                $media = new Media;
                $media->forceFill([
                    'uuid' => $uuid, 'created_by_id' => $actor->id,
                    'model_type' => $owner->getMorphClass(), 'model_id' => $owner->id,
                    'collection_name' => Media::ADMIN_COLLECTION, 'disk' => self::DISK,
                    'storage_key' => $key, 'mime_type' => $mime,
                    'original_name' => $this->originalName($file),
                    'size_bytes' => $file->getSize(), 'width' => $width, 'height' => $height,
                    'visibility' => MediaVisibilityEnum::PRIVATE, 'position' => 0,
                    'is_primary' => false, 'file_hash' => $hash,
                ])->save();

                activity()->causedBy($actor)->performedOn($media)->event('central_media_uploaded')
                    ->withProperties(['media_uuid' => $uuid, 'mime_type' => $mime, 'size_bytes' => $media->size_bytes])
                    ->log('Fichier central privé importé.');

                return $media;
            }, self::ATTEMPTS);
        } catch (Throwable $exception) {
            $this->cleanupUnregisteredFile($disk, $key, $uuid);
            throw $exception;
        }
    }

    public function archive(User $actor, Media $media, string $expected): void
    {
        $this->authorize($actor, 'archive', $media);

        DB::transaction(function () use ($actor, $media, $expected): void {
            $current = Media::whereKey($media->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, 'archive', $current);
            if (! hash_equals($current->managementState(), $expected)) {
                throw ValidationException::withMessages(['conflict' => 'Ce fichier a changé. Rechargez la page.']);
            }
            foreach (self::REFERENCES as $table => $columns) {
                foreach ($columns as $column) {
                    if ($current->getConnection()->table($table)->where($column, $current->id)->exists()) {
                        $this->fail('Ce fichier est une pièce métier référencée. Son archivage est interdit.');
                    }
                }
            }
            $current->delete();
            activity()->causedBy($actor)->performedOn($current)->event('central_media_archived')
                ->withProperties(['media_uuid' => $current->uuid])
                ->log('Fichier central archivé sans suppression des octets.');
        }, self::ATTEMPTS);
    }

    /** @return array{?int, ?int} */
    private function dimensions(UploadedFile $file, string $mime): array
    {
        if ($mime === 'application/pdf') {
            $handle = fopen($file->getPathname(), 'rb');
            if ($handle === false) {
                $this->fail('Le fichier ne peut pas être lu.');
            }
            try {
                if (fread($handle, 5) !== '%PDF-') {
                    $this->fail('Le contenu PDF est invalide.');
                }
            } finally {
                fclose($handle);
            }

            return [null, null];
        }

        // Une image tronquée peut émettre un avertissement : l'échec devient une erreur de validation.
        $dimensions = @getimagesize($file->getPathname());
        if ($dimensions === false || $dimensions[0] > self::MAX_DIMENSION || $dimensions[1] > self::MAX_DIMENSION) {
            $this->fail('L’image doit être valide et ne pas dépasser 4096 pixels par côté.');
        }

        return [$dimensions[0], $dimensions[1]];
    }

    private function originalName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));

        return Str::limit(preg_replace('/[\x00-\x1f\x7f]/u', '', $name) ?? 'fichier', 255, '');
    }

    private function authorize(User $actor, string $ability, ?Media $media = null): void
    {
        abort_if(tenancy()->initialized, 403);
        Gate::forUser($actor)->authorize($ability, $media ?? Media::class);
    }

    /** Préserve un fichier déjà enregistré, y compris si une erreur survient après le commit. */
    private function cleanupUnregisteredFile(FilesystemAdapter $disk, string $key, string $uuid): void
    {
        try {
            if (Media::withTrashed()->where('uuid', $uuid)->doesntExist() && $disk->exists($key) && ! $disk->delete($key)) {
                Log::warning('Central media cleanup failed.', ['media_uuid' => $uuid]);
            }
        } catch (Throwable $exception) {
            Log::warning('Central media cleanup unavailable.', ['media_uuid' => $uuid, 'exception_class' => $exception::class]);
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
