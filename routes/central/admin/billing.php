<?php

use App\Http\Controllers\Central\Admin\Billing\BillingRuleController;
use App\Http\Controllers\Central\Admin\Billing\SaasInvoiceController;
use Illuminate\Support\Facades\Route;

Route::controller(SaasInvoiceController::class)->prefix('billing/invoices')->name('invoices.')->whereUuid('invoice')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('{invoice}', 'show')->name('show');
    Route::post('{invoice}/number', 'reserve')->name('reserve');
});

Route::controller(SaasInvoiceController::class)->prefix('subscriptions/{subscription}')->group(function (): void {
    Route::get('installments', 'installments')->name('subscriptions.installments.index');
    Route::get('invoice/create', 'create')->name('invoices.create');
    Route::post('invoice', 'store')->name('invoices.store');
});

Route::controller(BillingRuleController::class)->prefix('billing')->group(function (): void {
    Route::get('sequences', 'sequences')->name('billing-sequences.index');
    Route::post('sequences', 'initializeSequence')->name('billing-sequences.initialize');
    Route::prefix('rules')->name('billing-rules.')->whereUuid('billingRule')->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{billingRule}/versions/create', 'createVersion')->name('versions.create');
        Route::post('{billingRule}/versions', 'storeVersion')->name('versions.store');
        Route::get('{billingRule}/edit', 'edit')->name('edit');
        Route::get('{billingRule}', 'show')->name('show');
        Route::put('{billingRule}', 'update')->name('update');
        Route::patch('{billingRule}/activation', 'activate')->name('activate');
        Route::patch('{billingRule}/validation', 'validateRule')->name('validate');
        Route::patch('{billingRule}/retirement', 'retire')->name('retire');
    });
});
