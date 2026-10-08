<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Model;

trait HasPermissionMetadata
{
    public static function bootHasPermissionMetadata(): void
    {
        static::creating(function (Model $permission): void {
            if ($permission->getAttribute('label') === null) {
                $permission->setAttribute('label', $permission->getAttribute('name'));
            }
        });
    }
}
