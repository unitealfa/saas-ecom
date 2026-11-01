<?php

use App\Models\Central\Audit\Activity;
use App\Models\Central\Permissions\Permission;
use Illuminate\Foundation\Testing\TestCase;

pest()->extend(TestCase::class);

test('central project configuration is nested while framework and package keys remain available', function (): void {
    expect(config('central.geography.reference.source'))->toBe('local-json:database/data/geography/dz')
        ->and(config('geography.reference'))->toBeNull()
        ->and(config('database.default'))->toBe('mysql')
        ->and(config('auth.guards.central.provider'))->toBe('central_users')
        ->and(config('fortify.guard'))->toBe('central')
        ->and(config('tenancy.database.central_connection'))->toBe('mysql')
        ->and(config('permission.models.permission'))->toBe(Permission::class)
        ->and(config('activitylog.activity_model'))->toBe(Activity::class);
});
