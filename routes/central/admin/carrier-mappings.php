<?php

use App\Http\Controllers\Central\Admin\Shipping\CarrierGeoMappingController;
use Illuminate\Support\Facades\Route;

Route::controller(CarrierGeoMappingController::class)
    ->prefix('carrier-mappings')->name('carrier-mappings.')->whereUuid('mapping')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('import', 'import')->name('import');
        Route::post('import/preview', 'preview')->name('preview');
        Route::post('import/confirm', 'confirm')->name('confirm');
        Route::get('{mapping}/edit', 'edit')->name('edit');
        Route::put('{mapping}', 'update')->name('update');
        Route::delete('{mapping}', 'archive')->name('archive');
    });
