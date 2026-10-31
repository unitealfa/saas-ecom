<?php

namespace App\Enums\Central\Geography;

enum GeoZoneTypeEnum: int
{
    case PROVINCE = 1;
    case MUNICIPALITY = 2;

    public function label(): string
    {
        return match ($this) {
            self::PROVINCE => 'Wilaya',
            self::MUNICIPALITY => 'Commune',
        };
    }
}