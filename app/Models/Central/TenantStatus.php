<?php

namespace App\Models\Central;

enum TenantStatus: int
{
    case Provisioning = 1;
    case Active = 2;
    case Inactive = 3;
    case Suspended = 4;
    case OverQuota = 5;
    case ProvisioningFailed = 6;
    case Deleted = 9;
}
