<?php

use App\Http\Controllers\Central\Admin\Countries\CountryController;
use Illuminate\Support\Facades\Route;

Route::controller(CountryController::class)->prefix('countries')->name('countries.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{country}/edit', 'edit')->name('edit');
    Route::put('{country}', 'update')->name('update');
    Route::patch('{country}/activation', 'activate')->name('activate');
    Route::delete('{country}', 'destroy')->name('destroy');
});
