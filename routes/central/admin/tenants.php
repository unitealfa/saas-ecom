<?php

use App\Http\Controllers\Central\Admin\Tenants\TenantController;
use Illuminate\Support\Facades\Route;

Route::controller(TenantController::class)->group(function (): void {
    Route::prefix('tenants')->name('tenants.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'chooseOwner')->name('create');
        Route::get('{tenant}', 'show')->withTrashed()->name('show');
        Route::get('{tenant}/edit', 'edit')->name('edit');
        Route::put('{tenant}', 'update')->name('update');
        Route::get('{tenant}/history', 'history')->withTrashed()->name('history');
        Route::post('{tenant}/suspend', 'suspend')->name('suspend');
        Route::post('{tenant}/activate', 'activate')->name('activate');
        Route::post('{tenant}/archive', 'archive')->name('archive');
        Route::put('{tenant}/priorities', 'priorities')->name('priorities');
    });

    Route::prefix('users/{user}/shops')->name('users.tenants.')->group(function (): void {
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
    });
});
