<?php

namespace App\Models\Central;

use App\Concerns\HasActivityMetadata;
use App\Concerns\HasPublicUuid;
use App\Enums\ActivityOriginEnum;
use Spatie\Activitylog\Models\Activity as PackageActivity;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class Activity extends PackageActivity
{
    use CentralConnection, HasActivityMetadata, HasPublicUuid;

    protected function casts(): array
    {
        return [...parent::casts(), 'tenant_id' => 'integer', 'origin' => ActivityOriginEnum::class];
    }
}
