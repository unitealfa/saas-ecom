<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

class PermissionAssignment extends MorphPivot
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['assigned_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $assignment): void {
            $assignedAt = $assignment->getAttribute('assigned_at') ?? now();
            $assignment->setAttribute('assigned_at', $assignedAt);
            if ($assignment->getTable() === config('permission.table_names.model_has_permissions')) {
                $assignment->setAttribute('expires_at', $assignment->getAttribute('expires_at') ?? CarbonImmutable::parse($assignedAt)->addDays(9999));
            }
        });
    }
}
