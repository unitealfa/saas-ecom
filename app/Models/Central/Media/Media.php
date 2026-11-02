<?php

namespace App\Models\Central\Media;

use App\Concerns\HasPublicUuid;
use App\Enums\MediaVisibilityEnum;
use App\Models\Central\Users\User;
use Carbon\CarbonInterface;
use Database\Factories\Central\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $uuid
 * @property int $model_id
 * @property string $model_type
 * @property int|null $created_by_id
 * @property string $collection_name
 * @property string $disk
 * @property string $storage_key
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property int|null $width
 * @property int|null $height
 * @property bool $is_primary
 * @property string|null $file_hash
 * @property MediaVisibilityEnum $visibility
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $deleted_at
 * @property-read User|null $creator
 */
#[Hidden(['id', 'model_id', 'created_by_id'])]
#[UseFactory(MediaFactory::class)]
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use CentralConnection, HasFactory, HasPublicUuid, SoftDeletes;

    public const string ADMIN_COLLECTION = 'administrative_documents';

    public const string STATE_PATTERN = '/^[a-f0-9]{64}$/D';

    private const array STATE_FIELDS = ['uuid', 'model_type', 'model_id', 'collection_name',
        'disk', 'storage_key', 'file_hash', 'visibility', 'is_primary', 'deleted_at', 'updated_at'];

    public function isAdministrativeDocument(): bool
    {
        return $this->model_type === 'central_user'
            && $this->collection_name === self::ADMIN_COLLECTION && ! $this->is_primary;
    }

    /** Empreinte des métadonnées pour refuser l'archivage d'un formulaire obsolète. */
    public function managementState(): string
    {
        return hash('sha256', json_encode($this->only(self::STATE_FIELDS), JSON_THROW_ON_ERROR));
    }

    protected function casts(): array
    {
        return [
            'model_id' => 'integer',
            'created_by_id' => 'integer',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'visibility' => MediaVisibilityEnum::class,
            'position' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id')->withTrashed();
    }
}
