<?php

use App\Http\Controllers\Central\Admin\Deployments\TenantDeploymentController;
use Illuminate\Support\Facades\Route;

Route::controller(TenantDeploymentController::class)->prefix('deployments')->name('deployments.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('{deployment}', 'show')->name('show');

    // Préfixe « deployments » : ces trois actions partagent un compteur qui leur est propre,
    // distinct de celui des autres routes limitées à 6 par minute.
    Route::middleware('throttle:6,1,deployments')->group(function (): void {
        Route::post('{deployment}/provision', 'provision')->name('provision');
        Route::post('{deployment}/migrate', 'migrate')->name('migrate');
        Route::post('{deployment}/retry', 'retry')->name('retry');
    });
});