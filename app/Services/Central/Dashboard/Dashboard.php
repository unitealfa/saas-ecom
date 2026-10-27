<?php

namespace App\Services\Central\Dashboard;

use App\Models\Central\Roles\Role;
use App\Models\Central\Users\User;
use Illuminate\Support\Collection;

class Dashboard
{
    /** @return array{shops: Collection<int, string>, staff: bool} */
    public function forUser(User $user): array
    {
        return [
            'shops' => $user->tenants()->orderBy('id')->pluck('shop_name'),
            'staff' => $user->roles()->where('guard_name', Role::GUARD)->exists(),
        ];
    }
}
