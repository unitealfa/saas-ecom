<?php

use App\Http\Controllers\Central\Admin\Media\CentralMediaController;
use Illuminate\Support\Facades\Route;

Route::controller(CentralMediaController::class)
    ->prefix('media')->name('media.')->whereUuid('media')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{media}/download', 'download')->middleware('signed:relative')->name('download');
        Route::get('{media}', 'show')->withTrashed()->name('show');
        Route::delete('{media}', 'archive')->name('archive');
    });
