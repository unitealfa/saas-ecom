<?php

namespace App\Http\Requests\Central\Media;

use App\Models\Central\Media\Media;
use App\Models\Central\Users\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ArchiveCentralMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user('central');
        $media = $this->route('media');

        return $actor instanceof User && $media instanceof Media && Gate::forUser($actor)->allows('archive', $media);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['expected_state' => ['required', 'string', 'regex:'.Media::STATE_PATTERN],
            ...array_fill_keys(['id', 'uuid', 'model_type', 'model_id', 'disk', 'storage_key',
                'file_hash', 'visibility', 'deleted_at', 'collection_name', 'created_by_id', 'is_primary'], ['prohibited'])];
    }
}
