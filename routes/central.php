<?php

use App\Http\Controllers\Central\Auth\RegistrationStatusController;
use App\Http\Controllers\Central\Dashboard\DashboardController;
use App\Http\Controllers\Central\Owner\Billing\CreditNoteController;
use App\Http\Controllers\Central\Owner\Billing\InvoiceController;
use App\Http\Controllers\Central\Owner\Billing\OwnerBillingOverviewController;
use App\Http\Controllers\Central\Owner\Billing\OwnerPaymentCorrectionController;
use App\Http\Controllers\Central\Owner\Billing\PaymentController;
use App\Http\Controllers\Central\Owner\Billing\SubscriptionPurchaseController;
use App\Http\Controllers\Central\Owner\Subscriptions\SubscriptionRequestController;
use App\Http\Controllers\Central\Owner\Tenants\FirstTenantController;
use App\Http\Controllers\Central\Owner\Tenants\TenantAccessController;
use App\Http\Controllers\Central\Owner\Tenants\TenantController as OwnerTenantController;
use App\Http\Middleware\Central\Auth\EnsureActiveCentralAccount;
use Illuminate\Support\Facades\Route;

Route::redirect('/', rtrim(config('app.url'), '/').'/login')->name('home');

require __DIR__.'/central/diagnostics.php';
require __DIR__.'/central/geography.php';

Route::get('inscription/statut', RegistrationStatusController::class)
    ->middleware(['auth:central', EnsureActiveCentralAccount::class.':optional', 'throttle:registration-status'])
    ->name('registration.status');

Route::middleware(['auth:central', 'verified', EnsureActiveCentralAccount::class])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::name('owner.')->group(function (): void {
        // C-157/C-158/C-159/C-162: owner overview and append-only correction requests.
        Route::get('facturation', OwnerBillingOverviewController::class)->name('billing.index');
        Route::controller(OwnerPaymentCorrectionController::class)
            ->prefix('facturation/corrections/{payment}')
            ->whereUuid('payment')
            ->name('billing.corrections.')
            ->group(function (): void {
                Route::get('/', 'create')->name('create');
                Route::post('/', 'store')->middleware('throttle:3,1')->name('store');
            });

        Route::controller(SubscriptionRequestController::class)->prefix('demandes-abonnement')->name('subscription-requests.')->whereUuid('subscriptionRequest')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{subscriptionRequest}', 'show')->name('show');
            Route::post('{subscriptionRequest}/invoice', 'prepareInvoice')->name('invoice');
            Route::patch('{subscriptionRequest}/choice', 'choice')->name('choice');
            Route::put('{subscriptionRequest}', 'revise')->name('revise');
            Route::post('{subscriptionRequest}/cancel', 'cancel')->name('cancel');
            Route::post('{subscriptionRequest}/proof', 'proof')->name('proof');
        });
        Route::controller(CreditNoteController::class)->prefix('avoirs')->name('credit-notes.')->whereUuid('credit')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{credit}', 'show')->name('show');
            Route::get('{credit}/pdf', 'download')->middleware('signed')->name('download');
        });
        Route::controller(SubscriptionPurchaseController::class)->prefix('abonnements')->name('subscriptions.')->whereUuid('subscription')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->middleware('throttle:6,1')->name('store');
            Route::get('{subscription}', 'show')->name('show');
        });
        Route::controller(PaymentController::class)->prefix('paiements')->name('payments.')->whereUuid('payment')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{payment}', 'show')->name('show');
            Route::get('{payment}/proof', 'proof')->middleware('signed')->name('proof');
            Route::get('{payment}/receipt', 'receipt')->middleware('signed')->name('receipt');
            Route::post('{payment}/proof', 'resumeProof')->middleware('throttle:6,1')->name('resume-proof');
        });
        Route::controller(PaymentController::class)->prefix('factures/{invoice}/justificatif')->name('payments.')->whereUuid('invoice')->group(function (): void {
            Route::get('/', 'create')->name('create');
            Route::post('/', 'store')->middleware('throttle:6,1')->name('store');
        });
        Route::controller(InvoiceController::class)->prefix('factures')->name('invoices.')->whereUuid('invoice')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{invoice}', 'show')->name('show');
            Route::get('{invoice}/pdf', 'download')->middleware('signed')->name('download');
        });
        Route::controller(FirstTenantController::class)->prefix('premiere-boutique')->group(function (): void {
            Route::get('/', 'create')->name('first-shop');
            Route::post('/', 'store')->middleware('throttle:6,1')->name('first-shop.store');
        });
        Route::controller(OwnerTenantController::class)->prefix('boutiques')->name('shops.')->group(function (): void {
            Route::get('creer', 'create')->name('create');
            Route::post('/', 'store')->middleware('throttle:6,1')->name('store');
            Route::post('{tenant}/reessayer', 'retry')->whereUuid('tenant')->middleware('throttle:3,1')->name('retry');
        });
        Route::get('boutiques/{tenant}/administrer', TenantAccessController::class)
            ->whereUuid('tenant')->name('shops.access');
    });
});

require __DIR__.'/central/admin.php';
require __DIR__.'/central/settings.php';
