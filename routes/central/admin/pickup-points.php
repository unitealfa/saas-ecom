<?php

use App\Http\Controllers\Central\Admin\Shipping\PickupPointController;
use Illuminate\Support\Facades\Route;

Route::controller(PickupPointController::class)
    ->prefix('pickup-points')->name('pickup-points.')->whereUuid('point')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('import', 'import')->name('import');
        Route::post('import/preview', 'preview')->name('preview');
        Route::post('import/confirm', 'confirm')->name('confirm');
        Route::get('{point}/edit', 'edit')->name('edit');
        Route::get('{point}', 'show')->withTrashed()->name('show');
        Route::put('{point}', 'update')->name('update');
        Route::patch('{point}/activation', 'activate')->name('activate');
        Route::delete('{point}', 'archive')->name('archive');
    });
