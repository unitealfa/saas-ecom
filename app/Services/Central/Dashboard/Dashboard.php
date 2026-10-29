<?php

namespace App\Services\Central\Dashboard;

use App\Models\Central\Users\User;
use App\Services\Central\Authorization\CentralUserAccess;
use Illuminate\Support\Collection;

class Dashboard
{
    public function __construct(private CentralUserAccess $access) {}

    /** @return array{shops: Collection<int, string>, staff: bool} */
    public function forUser(User $user): array
    {
        $staff = $this->access->isStaff($user);

        return [
            'shops' => $staff ? collect() : $user->tenants()->orderBy('id')->pluck('shop_name'),
            'staff' => $staff,
        ];
    }
}
