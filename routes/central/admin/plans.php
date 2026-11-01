<?php

use App\Http\Controllers\Central\Admin\Plans\PlanController;
use Illuminate\Support\Facades\Route;

Route::controller(PlanController::class)->prefix('plans')->name('plans.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::get('{plan}', 'show')->withTrashed()->name('show');
    Route::put('{plan}/features', 'updateFeatures')->name('features.update');
});
