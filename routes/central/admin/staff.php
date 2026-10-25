<?php

use App\Http\Controllers\Central\Admin\Staff\StaffController;
use App\Http\Controllers\Central\Admin\Staff\StaffPermissionController;
use App\Http\Controllers\Central\Admin\Staff\StaffRoleController;
use Illuminate\Support\Facades\Route;
use App\Models\Central\User;

Route::prefix('staff')->name('staff.')->group(function (): void {
    Route::livewire('/', 'central::pages.admin.staff.index')
        ->middleware('can:viewAnyStaff,'.User::class)->name('index');

    Route::controller(StaffController::class)->group(function (): void {
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{user}', 'show')->withTrashed()->name('show');
        Route::get('{user}/edit', 'edit')->name('edit');
        Route::put('{user}', 'update')->name('update');
        Route::post('{user}/invitation', 'resendInvitation')
            ->middleware('throttle:6,1')->name('invitation');
        Route::patch('{user}/status', 'changeStatus')->name('status');
        Route::delete('{user}', 'destroy')->name('destroy');
    });

    Route::controller(StaffRoleController::class)->prefix('{user}/roles')->name('roles.')->group(function (): void {
        Route::get('/', 'edit')->name('edit');
        Route::put('/', 'update')->name('update');
        Route::post('{role}/renew', 'renew')->name('renew');
    });

    Route::controller(StaffPermissionController::class)->prefix('{user}/permissions')->name('permissions.')->group(function (): void {
        Route::get('/', 'index')->withTrashed()->name('index');
        Route::put('/', 'update')->name('update');
    });
});
