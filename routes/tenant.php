<?php

use App\Http\Controllers\Storefront\ShopBrandingController;
use App\Http\Controllers\Tenant\Diagnostics\DatabaseDiagnosticsController;
use App\Http\Middleware\EnsureLocalDiagnostics;
use App\Http\Middleware\Tenant\EnsureTenantAvailable;
use App\Http\Middleware\Tenant\FinishTenantRequest;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    FinishTenantRequest::class,
    PreventAccessFromCentralDomains::class,
    EnsureTenantAvailable::class,
    InitializeTenancyByDomain::class,
    'web',
])->name('tenant.')->group(function (): void {
    require __DIR__.'/storefront.php';

    // Public images only: the controller verifies the image belongs to this shop.
    Route::get('/branding/{kind}', ShopBrandingController::class)
        ->whereIn('kind', ['logo', 'favicon'])->name('shop-profile.branding');

    // All administration routes in this file require auth:tenant and fresh Policies.
    require __DIR__.'/tenant/admin/shop-profile.php';

    Route::get('/_dev/database', DatabaseDiagnosticsController::class)
        ->middleware(EnsureLocalDiagnostics::class)->name('database-diagnostics');
});
