<?php

use App\Http\Controllers\Central\Admin\Billing\BillingRuleController;
use App\Http\Controllers\Central\Admin\Billing\OwnerPaymentCorrectionReviewController;
use App\Http\Controllers\Central\Admin\Billing\SaasCreditNoteController;
use App\Http\Controllers\Central\Admin\Billing\SaasInvoiceController;
use App\Http\Controllers\Central\Admin\Billing\SaasPaymentController;
use Illuminate\Support\Facades\Route;

// C-159: administrative correction inbox and review action (existing payment permissions).
Route::get('billing/payments/correction-requests', OwnerPaymentCorrectionReviewController::class)
    ->name('payments.corrections.index');
Route::post('billing/payments/correction-requests/{report}/review', [OwnerPaymentCorrectionReviewController::class, 'review'])
    ->whereUuid('report')
    ->name('payments.corrections.review');

Route::controller(SaasCreditNoteController::class)->prefix('billing/credit-notes')->name('credit-notes.')->whereUuid(['credit', 'invoice'])->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('create/{invoice}', 'create')->name('create');
    Route::post('invoice/{invoice}', 'store')->name('store');
    Route::get('{credit}', 'show')->name('show');
    Route::get('{credit}/edit', 'edit')->name('edit');
    Route::put('{credit}', 'update')->name('update');
    Route::post('{credit}/prepare', 'prepare')->name('prepare');
    Route::post('{credit}/issue', 'issue')->name('issue');
    Route::post('{credit}/cancel', 'cancel')->name('cancel');
    Route::get('{credit}/pdf', 'download')->middleware('signed')->name('download');
});

Route::controller(SaasPaymentController::class)->prefix('billing/payments')->name('payments.')->whereUuid('payment')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('{payment}', 'show')->name('show');
    Route::post('{payment}/verify', 'verify')->name('verify');
    Route::post('{payment}/reject', 'reject')->name('reject');
    Route::post('{payment}/reconcile', 'reconcile')->name('reconcile');
    Route::post('{payment}/resume-cash', 'resumeCash')->name('resume-cash');
    Route::get('{payment}/proof', 'proof')->middleware('signed')->name('proof');
    Route::get('{payment}/receipt', 'receipt')->middleware('signed')->name('receipt');
});
Route::controller(SaasPaymentController::class)->prefix('billing/invoices/{invoice}/cash')->name('payments.cash.')->whereUuid('invoice')->group(function (): void {
    Route::get('/', 'createCash')->name('create');
    Route::post('/', 'cash')->name('store');
});

Route::controller(SaasInvoiceController::class)->prefix('billing/invoices')->name('invoices.')->whereUuid('invoice')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('{invoice}', 'show')->name('show');
    Route::post('{invoice}/number', 'reserve')->name('reserve');
    Route::post('{invoice}/issue', 'issue')->name('issue');
    Route::get('{invoice}/pdf', 'download')->middleware('signed')->name('download');
});

Route::controller(SaasInvoiceController::class)->prefix('subscriptions/{subscription}')->group(function (): void {
    Route::get('installments', 'installments')->name('subscriptions.installments.index');
    Route::get('invoice/create', 'create')->name('invoices.create');
    Route::post('invoice', 'store')->name('invoices.store');
    Route::post('installments/{installment}/invoice', 'prepareInstallment')->whereUuid(['subscription', 'installment'])->name('invoices.installment.store');
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
