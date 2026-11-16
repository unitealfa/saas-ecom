<?php

namespace App\Policies\Central;

use App\Enums\Central\Users\StatusEnum;
use App\Models\Central\Notifications\DatabaseNotification;
use App\Models\Central\Users\User;

class NotificationPolicy
{
    public function viewAny(User $actor): bool
    {
        return ! tenancy()->initialized && ! $actor->trashed() && $actor->status === StatusEnum::ACTIVE && $actor->hasVerifiedEmail();
    }

    public function update(User $actor, DatabaseNotification $notification): bool
    {
        return $this->viewAny($actor) && (string) $notification->getAttribute('notifiable_id') === (string) $actor->id
            && $notification->getAttribute('notifiable_type') === $actor->getMorphClass();
    }
}
