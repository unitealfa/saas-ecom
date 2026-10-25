<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

class PermissionAssignment extends MorphPivot
{
    /** « Sans expiration » est représenté par une date très lointaine (environ 27 ans), jamais par null. */
    public const int NEVER_EXPIRES_DAYS = 9999;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'assigned_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $assignment): void {
            $assignedAt = $assignment->getAttribute('assigned_at') ?? now();
            $assignment->setAttribute('assigned_at', $assignedAt);

            if ($assignment->isDirectPermission()) {
                $assignment->setAttribute(
                    'expires_at',
                    $assignment->getAttribute('expires_at')
                        ?? CarbonImmutable::parse($assignedAt)->addDays(self::NEVER_EXPIRES_DAYS),
                );
            }
        });
    }

    private function isDirectPermission(): bool
    {
        return $this->getTable() === config('permission.table_names.model_has_permissions');
    }
}