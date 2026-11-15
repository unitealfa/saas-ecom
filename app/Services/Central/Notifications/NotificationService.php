<?php

namespace App\Services\Central\Notifications;

use App\Models\Central\Notifications\DatabaseNotification;
use App\Models\Central\Users\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class NotificationService
{
    /** @return LengthAwarePaginator<int, DatabaseNotification> */
    public function paginate(User $actor): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', DatabaseNotification::class);

        return $actor->notifications()->paginate(20);
    }

    public function markRead(User $actor, DatabaseNotification $notification): void
    {
        abort_unless((int) $notification->notifiable_id === $actor->id && $notification->notifiable_type === $actor->getMorphClass(), 403);
        Gate::forUser($actor)->authorize('update', $notification);
        $actor->notifications()->whereKey($notification->getKey())->whereNull('read_at')->update(['read_at' => now()]);
    }
}
