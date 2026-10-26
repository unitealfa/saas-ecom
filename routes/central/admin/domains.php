<?php

use App\Http\Controllers\Central\Admin\Domains\DomainController;
use Illuminate\Support\Facades\Route;

Route::controller(DomainController::class)->prefix('domains')->name('domains.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->middleware('throttle:30,1')->name('store');
    Route::get('{domain}/edit', 'edit')->name('edit');
    Route::put('{domain}', 'update')->middleware('throttle:30,1')->name('update');
    Route::post('{domain}/verification', 'verify')->middleware('throttle:6,1')->name('verify');
    Route::patch('{domain}/primary', 'primary')->middleware('throttle:30,1')->name('primary');
    Route::delete('{domain}', 'destroy')->middleware('throttle:30,1')->name('destroy');
    Route::post('{domain}/restore', 'restore')->withTrashed()->middleware('throttle:30,1')->name('restore');
});
