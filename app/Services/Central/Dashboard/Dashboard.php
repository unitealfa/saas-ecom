<?php

namespace App\Services\Central\Dashboard;

use App\Models\Central\Role;
use App\Models\Central\User;
use Illuminate\Support\Collection;

class Dashboard
{
    public function forUser(User $user): array
    {
        return [
            'shops' => $user->tenants()->orderBy('id')->pluck('shop_name'),
            'staff' => $user->roles()->where('guard_name', Role::GUARD)->exists(),
        ];
    }
}