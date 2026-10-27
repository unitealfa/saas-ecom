<?php

namespace App\Models\Central\Roles;

use App\Concerns\HasPublicUuid;
use App\Concerns\HasRoleMetadata;
use App\Concerns\LogsSafeActivity;
use App\Models\Central\Permissions\Permission;
use App\Models\Shared\Authorization\PermissionAssignment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role as PackageRole;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property string $label
 * @property int $permission_version
 * @property bool $is_super_admin
 * @property bool $is_system
 * @property bool $is_protected
 * @property string $permission_signature
 * @property PermissionAssignment $pivot
 */
#[Fillable(['name', 'label'])]
class Role extends PackageRole
{
    use CentralConnection, HasPublicUuid, LogsSafeActivity;

    /** @use HasRoleMetadata<Permission> */
    use HasRoleMetadata;

    public const string GUARD = 'central';

    protected string $guard_name = self::GUARD;

    protected $attributes = [
        'guard_name' => self::GUARD,
        'is_system' => false,
        'is_protected' => false,
        'is_super_admin' => false,
        'permission_version' => 1,
    ];

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'is_system' => 'boolean',
            'is_protected' => 'boolean',
            'is_super_admin' => 'boolean',
            'permission_version' => 'integer',
        ];
    }

    public function usesIndividualPermissions(): bool
    {
        return (bool) config('permission.central_profiles', false);
    }

    /** @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeForCentralGuard(Builder $query): Builder
    {
        return $query->where('guard_name', self::GUARD);
    }
}
