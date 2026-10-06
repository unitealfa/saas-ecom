<?php

use App\Http\Controllers\Tenant\DatabaseDiagnosticsController;
use App\Http\Controllers\Tenant\StorefrontController;
use App\Http\Middleware\EnsureLocalDiagnostics;
use App\Http\Middleware\FinishTenantRequest;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    FinishTenantRequest::class,
    PreventAccessFromCentralDomains::class,
    InitializeTenancyByDomain::class,
    'web',
])->group(function (): void {
    Route::get('/', StorefrontController::class)->name('tenant.home');
    Route::get('/_dev/database', DatabaseDiagnosticsController::class)
        ->middleware(EnsureLocalDiagnostics::class)->name('tenant.database-diagnostics');
});
