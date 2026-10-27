<?php

namespace App\Models\Central\Permissions;

use App\Concerns\HasPermissionMetadata;
use App\Concerns\HasPublicUuid;
use App\Concerns\LogsSafeActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission as PackagePermission;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $label
 */
#[Fillable(['name', 'label', 'feature_code'])]
class Permission extends PackagePermission
{
    use CentralConnection, HasPermissionMetadata, HasPublicUuid, LogsSafeActivity;

    public const string GUARD = 'central';

    protected string $guard_name = self::GUARD;

    protected $attributes = ['guard_name' => self::GUARD];

    /** @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeForCentralGuard(Builder $query): Builder
    {
        return $query->where('guard_name', self::GUARD);
    }
}
