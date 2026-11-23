<?php

use App\Http\Controllers\Storefront\ShopBrandingController;
use App\Http\Controllers\Tenant\Admin\Dashboard\DashboardController;
use App\Http\Controllers\Tenant\Auth\AcceptTeamInvitationController;
use App\Http\Controllers\Tenant\Auth\LoginController;
use App\Http\Controllers\Tenant\Auth\OwnerLoginController;
use App\Http\Controllers\Tenant\Auth\PasskeyController;
use App\Http\Controllers\Tenant\Auth\PasswordResetController;
use App\Http\Controllers\Tenant\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Tenant\Diagnostics\DatabaseDiagnosticsController;
use App\Http\Controllers\Tenant\Settings\AccountSettingsController;
use App\Http\Controllers\Tenant\Settings\EmailSettingsController;
use App\Http\Controllers\Tenant\Settings\ProfilePhotoController;
use App\Http\Controllers\Tenant\Settings\TwoFactorSettingsController;
use App\Http\Middleware\EnsureLocalDiagnostics;
use App\Http\Middleware\Tenant\EnsureActiveTenantAccount;
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
    Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge');
    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'verify'])->middleware('throttle:6,1')->name('two-factor.verify');
    Route::get('passkeys/login/options', [PasskeyController::class, 'loginOptions'])->name('passkeys.login.options');
    Route::post('passkeys/login', [PasskeyController::class, 'login'])->middleware('throttle:6,1')->name('passkeys.login');
    Route::get('forgot-password', [PasswordResetController::class, 'forgot'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:3,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
    Route::get('invitations/{invitation}/accept', [AcceptTeamInvitationController::class, 'show'])
        ->whereUuid('invitation')->name('invitations.show');
    Route::post('invitations/{invitation}/accept', [AcceptTeamInvitationController::class, 'accept'])
        ->whereUuid('invitation')->middleware('throttle:6,1')->name('invitations.accept');
    Route::post('invitations/{invitation}/changer-de-compte', [AcceptTeamInvitationController::class, 'switchAccount'])
        ->whereUuid('invitation')->middleware('auth:tenant')->name('invitations.switch-account');
    Route::get('auth/owner/start', [OwnerLoginController::class, 'start'])
        ->middleware('signed:relative')->block(10, 10)->name('owner-login.start');
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
        Route::get('settings', [AccountSettingsController::class, 'show'])->name('settings.show');
        Route::put('settings/profile', [AccountSettingsController::class, 'updateProfile'])->name('settings.profile');
        Route::put('settings/password', [AccountSettingsController::class, 'updatePassword'])->name('settings.password');
        Route::delete('settings/account', [AccountSettingsController::class, 'destroy'])->name('settings.destroy');
        Route::get('profile-photos/{user}', [ProfilePhotoController::class, 'show'])->whereUuid('user')->middleware('signed:relative')->name('profile-photo.show');
        Route::post('settings/photo', [ProfilePhotoController::class, 'update'])->name('profile-photo.update');
        Route::delete('settings/photo', [ProfilePhotoController::class, 'destroy'])->name('profile-photo.destroy');
        Route::post('settings/two-factor', [TwoFactorSettingsController::class, 'enable'])->name('settings.two-factor.enable');
        Route::post('settings/two-factor/confirm', [TwoFactorSettingsController::class, 'confirm'])->name('settings.two-factor.confirm');
        Route::post('settings/two-factor/recovery', [TwoFactorSettingsController::class, 'recovery'])->name('settings.two-factor.recovery');
        Route::delete('settings/two-factor', [TwoFactorSettingsController::class, 'disable'])->name('settings.two-factor.disable');
        Route::post('settings/email', [EmailSettingsController::class, 'requestChange'])->name('settings.email.request');
        Route::get('settings/email/confirm', [EmailSettingsController::class, 'confirm'])->middleware('signed:relative')->name('settings.email.confirm');
        Route::post('settings/passkeys/prepare', [PasskeyController::class, 'prepare'])->name('passkeys.prepare');
        Route::get('settings/passkeys/options', [PasskeyController::class, 'registrationOptions'])->name('passkeys.options');
        Route::post('settings/passkeys', [PasskeyController::class, 'register'])->name('passkeys.register');
        Route::delete('settings/passkeys/{uuid}', [PasskeyController::class, 'destroy'])->whereUuid('uuid')->name('passkeys.destroy');
        require __DIR__.'/tenant/admin/roles.php';
        require __DIR__.'/tenant/admin/permissions.php';
        require __DIR__.'/tenant/admin/team.php';
    });

    Route::get('/_dev/database', DatabaseDiagnosticsController::class)
        ->middleware(EnsureLocalDiagnostics::class)->name('database-diagnostics');
});
