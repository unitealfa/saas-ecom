<?php

namespace App\Models\Central;

use App\Concerns\HasPublicUuid;
use App\Enums\MediaVisibilityEnum;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

#[Hidden(['id', 'model_id', 'created_by_id'])]
class Media extends Model
{
    use CentralConnection, HasPublicUuid, SoftDeletes;

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

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id')->withTrashed();
    }
}
