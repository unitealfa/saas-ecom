<?php

use App\Http\Controllers\Central\Admin\Features\FeatureController;
use Illuminate\Support\Facades\Route;

Route::controller(FeatureController::class)->prefix('features')->name('features.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{feature}/edit', 'edit')->name('edit');
    Route::get('{feature}', 'show')->withTrashed()->name('show');
    Route::put('{feature}', 'update')->name('update');
    Route::patch('{feature}/activation', 'activate')->name('activate');
    Route::delete('{feature}', 'archive')->name('archive');
});
