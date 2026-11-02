<?php

use App\Http\Controllers\Central\Admin\Plans\PlanController;
use App\Http\Controllers\Central\Admin\Plans\PlanPageController;
use Illuminate\Support\Facades\Route;

Route::controller(PlanController::class)->prefix('plans')->name('plans.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::get('{plan}', 'show')->withTrashed()->name('show');
    Route::put('{plan}', 'update')->name('update');
    Route::put('{plan}/features', 'updateFeatures')->name('features.update');
    Route::post('{plan}/versions', 'storeVersion')->name('versions.store');
    Route::patch('{plan}/activation', 'activate')->name('activate');
    Route::delete('{plan}', 'archive')->name('archive');
});

Route::controller(PlanPageController::class)->prefix('offres')->name('plan-pages.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{plan}', 'show')->withTrashed()->name('show');
    Route::put('{plan}', 'update')->name('update');
    Route::put('{plan}/features', 'updateFeatures')->name('features.update');
    Route::post('{plan}/versions', 'storeVersion')->name('versions.store');
    Route::patch('{plan}/activation', 'activate')->name('activate');
    Route::delete('{plan}', 'archive')->name('archive');
});
