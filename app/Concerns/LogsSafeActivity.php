<?php

namespace App\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

trait LogsSafeActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['uuid', 'name', 'label', 'first_name', 'last_name', 'status', 'membership_status', 'shop_name', 'slug', 'permission_signature', 'permission_version'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
