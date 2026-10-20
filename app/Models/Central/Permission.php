<?php

namespace App\Models\Central;

use App\Concerns\HasPermissionMetadata;
use App\Concerns\HasPublicUuid;
use App\Concerns\LogsSafeActivity;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission as PackagePermission;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $uuid
 */
class Permission extends PackagePermission
{
    use CentralConnection, HasPermissionMetadata, HasPublicUuid, LogsSafeActivity;

    protected string $guard_name = 'central';

    protected $attributes = ['guard_name' => 'central'];

    protected $fillable = ['name', 'label', 'feature_code'];

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeForCentralGuard(Builder $query): Builder
    {
        return $query->where('guard_name', 'central');
    }
}
