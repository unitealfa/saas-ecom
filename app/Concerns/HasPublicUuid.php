<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;

trait HasPublicUuid
{
    public static function bootHasPublicUuid(): void
    {
        static::creating(function (Model $model): void {
            $uuid = $model->getAttribute('uuid') ?? Str::uuid()->toString();

            if (! is_string($uuid) || ! Str::isUuid($uuid, version: 4)) {
                throw new LogicException('A public identifier must be a UUID v4.');
            }

            $model->setAttribute('uuid', Str::lower($uuid));
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('uuid')) {
                throw new LogicException('Public identifiers cannot be changed.');
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
