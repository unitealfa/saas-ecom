<?php

use App\Http\Middleware\Central\Auth\EnsureActiveCentralAccount;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:central', 'verified', EnsureActiveCentralAccount::class])
    ->prefix('admin')->name('central.')
    ->whereUuid(['user', 'role', 'country', 'tenant', 'domain', 'deployment', 'feature'])->group(function (): void {
        require __DIR__.'/admin/users.php';
        require __DIR__.'/admin/staff.php';
        require __DIR__.'/admin/tenants.php';
        require __DIR__.'/admin/domains.php';
        require __DIR__.'/admin/deployments.php';
        require __DIR__.'/admin/features.php';
        require __DIR__.'/admin/roles.php';
        require __DIR__.'/admin/permissions.php';
        require __DIR__.'/admin/countries.php';
    });
