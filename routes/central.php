<?php

use App\Http\Controllers\Central\Auth\RegistrationStatusController;
use App\Http\Controllers\Central\Dashboard\DashboardController;
use App\Http\Controllers\Central\Owner\Tenants\FirstTenantController;
use App\Http\Controllers\Central\Owner\Tenants\TenantController as OwnerTenantController;
use App\Http\Middleware\Central\Auth\EnsureActiveCentralAccount;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

require __DIR__.'/central/diagnostics.php';

Route::get('inscription/statut', RegistrationStatusController::class)
    ->middleware(['auth:central', EnsureActiveCentralAccount::class.':optional'])->name('registration.status');

Route::middleware(['auth:central', 'verified', EnsureActiveCentralAccount::class])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('premiere-boutique', [FirstTenantController::class, 'create'])->name('owner.first-shop');
    Route::post('premiere-boutique', [FirstTenantController::class, 'store'])->middleware('throttle:6,1')->name('owner.first-shop.store');
    Route::get('boutiques/creer', [OwnerTenantController::class, 'create'])->name('owner.shops.create');
    Route::post('boutiques', [OwnerTenantController::class, 'store'])->middleware('throttle:6,1')->name('owner.shops.store');
    Route::post('boutiques/{tenant}/reessayer', [OwnerTenantController::class, 'retry'])->middleware('throttle:3,1')->name('owner.shops.retry');

    require __DIR__.'/central/admin.php';
});

require __DIR__.'/central/settings.php';
