<?php

namespace App\Enums\Central\Users;

enum JourneyState: string
{
    case VERIFICATION = 'verification';
    case PENDING = 'pending';
    case REJECTED = 'rejected';
    case SETUP = 'setup';
    case PREPARING = 'preparing';
    case READY = 'ready';

    public function routeName(): string
    {
        return match ($this) {
            self::VERIFICATION => 'verification.notice',
            self::SETUP => 'owner.first-shop',
            self::READY => 'dashboard',
            self::PENDING, self::REJECTED, self::PREPARING => 'registration.status',
        };
    }
}
