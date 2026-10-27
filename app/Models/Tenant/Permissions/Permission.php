<?php

namespace App\Models\Tenant\Permissions;

use App\Concerns\HasPermissionMetadata;
use App\Concerns\HasPublicUuid;
use App\Concerns\LogsSafeActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Spatie\Permission\Models\Permission as PackagePermission;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

#[Fillable(['name', 'label', 'feature_code'])]
class Permission extends PackagePermission
{
    use HasPermissionMetadata, HasPublicUuid, LogsSafeActivity, TenantConnection;

    public const string GUARD = 'tenant';

    protected string $guard_name = self::GUARD;

    protected $attributes = ['guard_name' => self::GUARD];
}
