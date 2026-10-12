<?php

use App\Http\Controllers\Tenant\Diagnostics\DatabaseDiagnosticsController;
use App\Http\Middleware\EnsureLocalDiagnostics;
use App\Http\Middleware\Tenant\FinishTenantRequest;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    FinishTenantRequest::class,
    PreventAccessFromCentralDomains::class,
    InitializeTenancyByDomain::class,
    'web',
])->group(function (): void {
    require __DIR__.'/storefront.php';
    Route::get('/_dev/database', DatabaseDiagnosticsController::class)
        ->middleware(EnsureLocalDiagnostics::class)->name('tenant.database-diagnostics');
});
