<?php

use App\Http\Controllers\Central\Auth\RegistrationStatusController;
use App\Http\Controllers\Central\Dashboard\DashboardController;
use App\Http\Controllers\Central\Owner\Tenants\FirstTenantController;
use App\Http\Controllers\Central\Owner\Tenants\TenantController as OwnerTenantController;
use App\Http\Middleware\Central\Auth\EnsureActiveCentralAccount;
use Illuminate\Support\Facades\Route;

Route::redirect('/', rtrim(config('app.url'), '/').'/login')->name('home');

require __DIR__.'/central/diagnostics.php';

Route::get('inscription/statut', RegistrationStatusController::class)
    ->middleware(['auth:central', EnsureActiveCentralAccount::class.':optional'])->name('registration.status');

Route::middleware(['auth:central', 'verified', EnsureActiveCentralAccount::class])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::name('owner.')->group(function (): void {
        Route::controller(FirstTenantController::class)->prefix('premiere-boutique')->group(function (): void {
            Route::get('/', 'create')->name('first-shop');
            Route::post('/', 'store')->middleware('throttle:6,1')->name('first-shop.store');
        });

        Route::controller(OwnerTenantController::class)->prefix('boutiques')->name('shops.')->group(function (): void {
            Route::get('creer', 'create')->name('create');
            Route::post('/', 'store')->middleware('throttle:6,1')->name('store');
            Route::post('{tenant}/reessayer', 'retry')->whereUuid('tenant')->middleware('throttle:3,1')->name('retry');
        });
    });
});

require __DIR__.'/central/admin.php';
require __DIR__.'/central/settings.php';
