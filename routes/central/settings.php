<?php

use App\Http\Controllers\Central\Settings\ProfilePhotoController;
use App\Http\Middleware\Central\Auth\EnsureActiveCentralAccount;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:central', EnsureActiveCentralAccount::class])->group(function () {
    Route::controller(\App\Http\Controllers\Central\Settings\UserContactController::class)->group(function () {
        Route::get('settings/contacts', 'index')->name('central.contacts.index');
        Route::post('settings/contacts', 'store')->middleware('throttle:6,1')->name('central.contacts.store');
        Route::put('settings/contacts/{contact}/primary', 'primary')->name('central.contacts.primary');
        Route::delete('settings/contacts/{contact}', 'destroy')->name('central.contacts.destroy');
        Route::post('settings/contacts/{contact}/verify', 'resend')->middleware('throttle:6,1')->name('central.contacts.resend');
        Route::get('settings/contacts/{contact}/verify/{hash}', 'verify')->middleware(['signed', 'throttle:6,1'])->name('central.contacts.verify');
    });
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
