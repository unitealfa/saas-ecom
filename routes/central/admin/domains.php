<?php

use App\Http\Controllers\Central\Admin\Domains\DomainController;
use Illuminate\Support\Facades\Route;

Route::controller(DomainController::class)->prefix('domains')->name('domains.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{domain}/edit', 'edit')->name('edit');
    Route::put('{domain}', 'update')->name('update');
    Route::post('{domain}/verification', 'verify')->name('verify');
    Route::patch('{domain}/primary', 'primary')->name('primary');
    Route::delete('{domain}', 'destroy')->name('destroy');
    Route::post('{domain}/restore', 'restore')->withTrashed()->name('restore');
});
