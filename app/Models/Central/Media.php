<?php

namespace App\Models\Central;

use App\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Media extends Model
{
    use CentralConnection, HasPublicUuid, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'model_id' => 'integer', 'created_by_id' => 'integer', 'size_bytes' => 'integer',
            'width' => 'integer', 'height' => 'integer', 'visibility' => 'integer',
            'position' => 'integer', 'is_primary' => 'boolean',
        ];
    }
}
