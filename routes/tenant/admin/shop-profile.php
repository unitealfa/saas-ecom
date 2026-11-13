<?php

use App\Http\Controllers\Tenant\Admin\Shop\ShopAddressController;
use App\Http\Controllers\Tenant\Admin\Shop\ShopProfileController;
use App\Http\Controllers\Tenant\Admin\Shop\ShopSocialLinkController;
use Illuminate\Support\Facades\Route;

// This file is loaded only from routes/tenant.php, AFTER tenant initialization.
// Auth is mandatory. T-001 must provide the tenant login flow before this UI becomes usable.
Route::middleware('auth:tenant')->prefix('admin/shop')->name('admin.shop.')->group(function (): void {
    Route::get('profile', [ShopProfileController::class, 'index'])->name('profile');
    Route::put('profile', [ShopProfileController::class, 'update'])->name('profile.update');

    Route::controller(ShopAddressController::class)->prefix('addresses')->name('addresses.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{address}/edit', 'edit')->whereUuid('address')->name('edit');
        Route::put('{address}', 'update')->whereUuid('address')->name('update');
        Route::delete('{address}', 'destroy')->whereUuid('address')->name('archive');
    });

    Route::controller(ShopSocialLinkController::class)->prefix('social-links')->name('social-links.')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{link}/edit', 'edit')->whereUuid('link')->name('edit');
        Route::put('{link}', 'update')->whereUuid('link')->name('update');
        Route::delete('{link}', 'destroy')->whereUuid('link')->name('archive');
    });
});
