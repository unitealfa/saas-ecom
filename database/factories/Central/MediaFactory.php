<?php

namespace Database\Factories\Central;

use App\Enums\MediaVisibilityEnum;
use App\Models\Central\Media\Media;
use App\Models\User;
use App\Services\Central\Media\CentralMediaService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Media> */
class MediaFactory extends Factory
{
    public const string PDF_CONTENT = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";

    protected $model = Media::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(), 'model_type' => 'central_user', 'model_id' => User::factory(),
            'created_by_id' => fn (array $attributes): int => $attributes['model_id'],
            'collection_name' => Media::ADMIN_COLLECTION, 'disk' => CentralMediaService::DISK,
            'storage_key' => fn (array $attributes): string => CentralMediaService::DIRECTORY.'/'.$attributes['uuid'].'.pdf',
            'mime_type' => 'application/pdf', 'original_name' => 'document-test.pdf',
            'size_bytes' => strlen(self::PDF_CONTENT), 'width' => null, 'height' => null,
            'visibility' => MediaVisibilityEnum::PRIVATE, 'position' => 0, 'is_primary' => false,
            'file_hash' => hash('sha256', self::PDF_CONTENT),
        ];
    }
}
