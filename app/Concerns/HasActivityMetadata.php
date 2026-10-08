<?php

namespace App\Concerns;

use App\Enums\ActivityOriginEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @mixin Model
 *
 * @method static void creating(\Closure $callback)
 */
trait HasActivityMetadata
{
    public static function bootHasActivityMetadata(): void
    {
        static::creating(function (Model $activity): void {
            $activity->setAttribute('correlation_id', $activity->getAttribute('correlation_id') ?? Str::uuid()->toString());
            $activity->setAttribute('origin', $activity->getAttribute('origin') ?? (app()->runningInConsole() ? ActivityOriginEnum::JOB : ActivityOriginEnum::USER));
        });
    }
}
