<?php

use App\Http\Controllers\Central\Admin\Shipping\ShippingCarrierController;
use Illuminate\Support\Facades\Route;

Route::controller(ShippingCarrierController::class)->prefix('carriers')->name('carriers.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{carrier}', 'show')->withTrashed()->name('show');
    Route::get('{carrier}/edit', 'edit')->name('edit');
    Route::put('{carrier}', 'update')->name('update');
    Route::patch('{carrier}/activation', 'activate')->name('activate');
    Route::delete('{carrier}', 'archive')->name('archive');
});
