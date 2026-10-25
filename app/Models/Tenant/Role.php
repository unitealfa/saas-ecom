<?php

namespace App\Models\Tenant;

use App\Concerns\HasPublicUuid;
use App\Concerns\HasRoleMetadata;
use App\Concerns\LogsSafeActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Spatie\Permission\Models\Role as PackageRole;
use Stancl\Tenancy\Database\Concerns\TenantConnection;


#[Fillable(['name', 'label'])]
class Role extends PackageRole
{
    use HasPublicUuid, HasRoleMetadata, LogsSafeActivity, TenantConnection;

    public const string GUARD = 'tenant';

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
}