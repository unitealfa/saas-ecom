<?php

namespace App\Models\Tenant;

use App\Concerns\HasPermissionMetadata;
use App\Concerns\HasPublicUuid;
use App\Concerns\LogsSafeActivity;
use Spatie\Permission\Models\Permission as PackagePermission;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class Permission extends PackagePermission
{
    use HasPermissionMetadata, HasPublicUuid, LogsSafeActivity, TenantConnection;

    protected string $guard_name = 'tenant';

    protected $attributes = ['guard_name' => 'tenant'];

    protected $fillable = ['name', 'label', 'feature_code'];
}
