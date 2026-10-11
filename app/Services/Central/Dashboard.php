<?php

namespace App\Services\Central;

use App\Models\Central\User;
use Illuminate\Support\Collection;

class Dashboard
{
    /** @return array{shops: Collection<int, string>, staff: bool} */
    public function forUser(User $user): array
    {
        return [
            'shops' => $user->tenants()->orderBy('id')->pluck('shop_name'),
            'staff' => $user->roles()->where('guard_name', 'central')->exists(),
        ];
    }
}
