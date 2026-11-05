<?php

use App\Http\Controllers\Central\Admin\Quotas\QuotaController;
use Illuminate\Support\Facades\Route;

Route::controller(QuotaController::class)->prefix('quotas')->name('quotas.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('{user}', 'show')->name('show');
    Route::post('{user}/reconcile', 'reconcile')->name('reconcile');
    Route::get('{user}/history', 'history')->name('history');
});
