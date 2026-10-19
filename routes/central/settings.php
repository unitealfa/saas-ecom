<?php

use App\Http\Controllers\Central\Settings\ProfilePhotoController;
use App\Http\Middleware\Central\Auth\EnsureActiveCentralAccount;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:central', EnsureActiveCentralAccount::class])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
    Route::get('profile-photos/{user}', [ProfilePhotoController::class, 'show'])
        ->withTrashed()->middleware('signed:relative')->name('central.profile-photo.show');
    Route::post('settings/profile/photo', [ProfilePhotoController::class, 'update'])->name('central.profile-photo.update');
    Route::delete('settings/profile/photo', [ProfilePhotoController::class, 'destroy'])->name('central.profile-photo.destroy');
});

Route::middleware(['auth:central', 'verified', EnsureActiveCentralAccount::class])->group(function () {
    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

    Route::livewire('settings/security', 'pages::settings.security')
        ->middleware([
            'password.confirm',
        ])
        ->name('security.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
