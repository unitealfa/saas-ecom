<?php

use App\Http\Controllers\Storefront\ShopBrandingController;
use App\Http\Controllers\Tenant\Admin\Dashboard\DashboardController;
use App\Http\Controllers\Tenant\Auth\OwnerLoginController;
use App\Http\Controllers\Tenant\Auth\LoginController;
use App\Http\Controllers\Tenant\Auth\PasswordResetController;
use App\Http\Controllers\Tenant\Auth\AcceptTeamInvitationController;
use App\Http\Middleware\Tenant\EnsureActiveTenantAccount;
use App\Http\Controllers\Tenant\Diagnostics\DatabaseDiagnosticsController;
use App\Http\Middleware\EnsureLocalDiagnostics;
use App\Http\Middleware\Tenant\EnsureTenantAvailable;
use App\Http\Middleware\Tenant\FinishTenantRequest;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    FinishTenantRequest::class,
    PreventAccessFromCentralDomains::class,
    EnsureTenantAvailable::class,
    InitializeTenancyByDomain::class,
    'web',
])->name('tenant.')->group(function (): void {
    require __DIR__.'/storefront.php';
    Route::get('login', [OwnerLoginController::class, 'show'])->name('login');
    Route::post('login', LoginController::class)->middleware('throttle:6,1')->name('login.store');
    Route::get('forgot-password', [PasswordResetController::class, 'forgot'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:3,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
    Route::get('invitations/{invitation}/accept', [AcceptTeamInvitationController::class, 'show'])
        ->whereUuid('invitation')->name('invitations.show');
    Route::post('invitations/{invitation}/accept', [AcceptTeamInvitationController::class, 'accept'])
        ->whereUuid('invitation')->middleware('throttle:6,1')->name('invitations.accept');
    Route::get('auth/owner', [OwnerLoginController::class, 'accept'])
        ->middleware('signed:relative')->block(10, 10)->name('owner-login');
    Route::post('logout', [OwnerLoginController::class, 'logout'])
        ->middleware('auth:tenant')->name('logout');
    Route::get('admin', DashboardController::class)
        ->middleware(['auth:tenant', EnsureActiveTenantAccount::class])->name('dashboard');

    // Public images only: the controller verifies the image belongs to this shop.
    Route::get('/branding/{kind}', ShopBrandingController::class)
        ->whereIn('kind', ['logo', 'favicon'])->name('shop-profile.branding');

    // All administration routes in this file require auth:tenant and fresh Policies.
    require __DIR__.'/tenant/admin/shop-profile.php';

    Route::middleware(['auth:tenant', EnsureActiveTenantAccount::class])->group(function (): void {
        require __DIR__.'/tenant/admin/roles.php';
        require __DIR__.'/tenant/admin/permissions.php';
        require __DIR__.'/tenant/admin/team.php';
    });

    Route::get('/_dev/database', DatabaseDiagnosticsController::class)
        ->middleware(EnsureLocalDiagnostics::class)->name('database-diagnostics');
});
