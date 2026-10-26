<?php

use App\Http\Controllers\Central\Auth\PasskeyEndpointsController;
use App\Http\Controllers\Central\Settings\ProfilePhotoController;
use App\Http\Middleware\Central\Auth\EnsureActiveCentralAccount;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:central', EnsureActiveCentralAccount::class])->group(function () {
    Route::prefix('settings')->group(function (): void {
        Route::redirect('/', '/settings/profile');
        Route::livewire('profile', 'pages::settings.profile')->name('profile.edit');
    });

    Route::controller(ProfilePhotoController::class)->name('central.profile-photo.')->group(function (): void {
        Route::get('profile-photos/{user}', 'show')
            ->whereUuid('user')->withTrashed()->middleware('signed:relative')->name('show');

        Route::prefix('settings/profile/photo')->group(function (): void {
            Route::post('/', 'update')->name('update');
            Route::delete('/', 'destroy')->name('destroy');
        });
    });
});

Route::middleware(['auth:central', 'verified', EnsureActiveCentralAccount::class])->prefix('settings')->group(function (): void {
    Route::livewire('appearance', 'pages::settings.appearance')->name('appearance.edit');

    Route::livewire('security', 'pages::settings.security')
        ->middleware('password.confirm')->name('security.edit');
});

Route::get('.well-known/passkey-endpoints', PasskeyEndpointsController::class)->name('well-known.passkeys');
