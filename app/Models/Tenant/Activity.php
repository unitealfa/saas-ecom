<?php

namespace App\Models\Tenant;

use App\Concerns\HasActivityMetadata;
use App\Concerns\HasPublicUuid;
use App\Enums\ActivityOriginEnum;
use Spatie\Activitylog\Models\Activity as PackageActivity;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class Activity extends PackageActivity
{
    use HasActivityMetadata, HasPublicUuid, TenantConnection;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [...parent::casts(), 'origin' => ActivityOriginEnum::class];
    }
}
