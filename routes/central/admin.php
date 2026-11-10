<?php

use App\Http\Middleware\Central\Auth\EnsureActiveCentralAccount;
use App\Http\Controllers\Central\Admin\Dashboard\DashboardController as AdminDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:central', 'verified', EnsureActiveCentralAccount::class])
    ->prefix('admin')
    ->name('central.')
    ->whereUuid([
        'user',
        'role',
        'country',
        'tenant',
        'domain',
        'deployment',
        'feature',
        'carrier',
        'plan',
        'subscription',
    ])
    ->group(function (): void {
        Route::get('dashboard', AdminDashboardController::class)->name('admin-dashboard.index');
        require __DIR__.'/admin/carriers.php';
        require __DIR__.'/admin/carrier-mappings.php';
        require __DIR__.'/admin/pickup-points.php';
        require __DIR__.'/admin/media.php';
        require __DIR__.'/admin/deliveries.php';
        require __DIR__.'/admin/billing.php';
        require __DIR__.'/admin/users.php';
        require __DIR__.'/admin/staff.php';
        require __DIR__.'/admin/tenants.php';
        require __DIR__.'/admin/domains.php';
        require __DIR__.'/admin/deployments.php';
        require __DIR__.'/admin/features.php';
        require __DIR__.'/admin/plans.php';
        require __DIR__.'/admin/subscriptions.php';
        require __DIR__.'/admin/activity.php';
        require __DIR__.'/admin/quotas.php';
        require __DIR__.'/admin/roles.php';
        require __DIR__.'/admin/permissions.php';
        require __DIR__.'/admin/countries.php';
        require __DIR__.'/admin/geography.php';
    });
