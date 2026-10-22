<?php

use App\Http\Controllers\Central\Admin\Users\LegalVerificationController;
use App\Http\Controllers\Central\Admin\Users\UserApprovalController;
use App\Http\Controllers\Central\Admin\Users\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('users')->name('users.')->group(function (): void {
    Route::livewire('/', 'central::pages.admin.users.index')
        ->middleware('can:viewAny,App\\Models\\User')->name('index');

    Route::controller(UserApprovalController::class)->group(function (): void {
        Route::get('pending', 'index')->name('pending');
        Route::post('{user}/approve', 'approve')->name('approve');
        Route::post('{user}/reject', 'reject')->name('reject');
    });

    Route::controller(UserController::class)->group(function (): void {
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{user}', 'show')->withTrashed()->name('show');
        Route::get('{user}/history', 'history')->withTrashed()->name('history');
        Route::get('{user}/edit', 'edit')->name('edit');
        Route::put('{user}', 'update')->name('update');
        Route::patch('{user}/status', 'changeStatus')->name('status');
        Route::delete('{user}', 'destroy')->name('destroy');
        Route::post('{user}/invitation', 'resendInvitation')
            ->middleware('throttle:6,1')->name('invitation');
    });

    Route::controller(LegalVerificationController::class)->prefix('{user}/legal')->name('legal.')->group(function (): void {
        Route::post('validate', 'validateProfile')->name('validate');
        Route::post('reject', 'rejectProfile')->name('reject');
    });
});
