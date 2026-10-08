<?php

namespace App\Models\Central;

use App\Concerns\HasPublicUuid;
use App\Concerns\HasRoleMetadata;
use App\Concerns\LogsSafeActivity;
use Spatie\Permission\Models\Role as PackageRole;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Role extends PackageRole
{
    use CentralConnection, HasPublicUuid, HasRoleMetadata, LogsSafeActivity;

    protected string $guard_name = 'central';

    protected $attributes = ['guard_name' => 'central', 'is_system' => false, 'is_protected' => false, 'is_super_admin' => false, 'permission_version' => 1];

    protected $fillable = ['name', 'label'];

    /** @return array<string, string> */
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
