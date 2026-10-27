<?php

namespace App\DTOs\Central\Users;

use App\Models\Central\Users\User;

final readonly class UserSaveResult
{
    public function __construct(public User $user, public ?bool $emailSent = null) {}
}
