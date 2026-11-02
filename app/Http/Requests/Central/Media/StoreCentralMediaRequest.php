<?php

namespace App\Http\Requests\Central\Media;

use App\Models\Central\Media\Media;
use App\Models\Central\Users\User;
use App\Services\Central\Media\CentralMediaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreCentralMediaRequest extends FormRequest
{
    private const array FORBIDDEN_FIELDS = ['id', 'uuid', 'created_by_id', 'model_id', 'model_type',
        'collection_name', 'disk', 'storage_key', 'mime_type', 'size_bytes', 'visibility',
        'position', 'is_primary', 'primary_slot', 'file_hash', 'deleted_at', 'width', 'height', 'duration_seconds'];

    public function authorize(): bool
    {
        $actor = $this->user('central');

        return $actor instanceof User && Gate::forUser($actor)->allows('upload', Media::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [...CentralMediaService::uploadRules(), ...array_fill_keys(self::FORBIDDEN_FIELDS, ['prohibited'])];
    }

    protected function getRedirectUrl(): string
    {
        return route('central.media.create');
    }
}
