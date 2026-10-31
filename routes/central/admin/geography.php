<?php

use App\Http\Controllers\Central\Admin\Geography\GeographyController;
use Illuminate\Support\Facades\Route;

Route::controller(GeographyController::class)
    ->prefix('geography')
    ->name('geography.')
    ->whereUuid('area')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');

        // La fiche reste consultable pour une zone archivée.
        Route::get('{area}', 'show')->withTrashed()->name('show');

        Route::get('{area}/edit', 'edit')->name('edit');
        Route::put('{area}', 'update')->name('update');
        Route::patch('{area}/activation', 'activate')->name('activate');
        Route::delete('{area}', 'archive')->name('archive');
    });
