<?php

namespace App\Models\Central;

use App\Concerns\HasPublicUuid;
use App\Concerns\HasRoleMetadata;
use App\Concerns\LogsSafeActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role as PackageRole;
use Stancl\Tenancy\Database\Concerns\CentralConnection;


#[Fillable(['name', 'label'])]
class Role extends PackageRole
{
    use CentralConnection, HasPublicUuid, HasRoleMetadata, LogsSafeActivity;

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


    public function scopeForCentralGuard(Builder $query): Builder
    {
        return $query->where('guard_name', self::GUARD);
    }
}