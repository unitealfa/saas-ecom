<?php

namespace App\Enums\Central\Subscriptions;

enum RequestFilterEnum: string
{
    case ALL = 'all';
    case ONGOING = 'ongoing';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ALL => 'Toutes', self::ONGOING => 'En cours', self::ACCEPTED => 'Acceptées',
            self::REJECTED => 'Refusées', self::CANCELLED => 'Annulées',
        };
    }

    /** @return list<RequestStatusEnum> */
    public function statuses(): array
    {
        return match ($this) {
            self::ALL => RequestStatusEnum::cases(),
            self::ONGOING => [RequestStatusEnum::INVOICE_PENDING, RequestStatusEnum::AWAITING_PAYMENT, RequestStatusEnum::REVIEW],
            self::ACCEPTED => [RequestStatusEnum::ACCEPTED], self::REJECTED => [RequestStatusEnum::REJECTED],
            self::CANCELLED => [RequestStatusEnum::CANCELLED],
        };
    }
}
