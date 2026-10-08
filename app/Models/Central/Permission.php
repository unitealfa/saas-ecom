<?php

namespace App\Models\Central;

use App\Concerns\HasPermissionMetadata;
use App\Concerns\HasPublicUuid;
use App\Concerns\LogsSafeActivity;
use Spatie\Permission\Models\Permission as PackagePermission;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Permission extends PackagePermission
{
    use CentralConnection, HasPermissionMetadata, HasPublicUuid, LogsSafeActivity;

    protected string $guard_name = 'central';

    protected $attributes = ['guard_name' => 'central'];

    protected $fillable = ['name', 'label', 'feature_code'];
}
