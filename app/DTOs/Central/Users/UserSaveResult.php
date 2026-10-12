<?php

namespace App\DTOs\Central\Users;

use App\Models\Central\User;

final readonly class UserSaveResult
{
    public function __construct(public User $user, public ?bool $emailSent = null) {}
}
