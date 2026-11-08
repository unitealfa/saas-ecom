<?php

use App\Http\Controllers\Central\Admin\Subscriptions\SubscriptionController;
use App\Http\Controllers\Central\Admin\Subscriptions\SubscriptionRequestController;
use Illuminate\Support\Facades\Route;

Route::controller(SubscriptionRequestController::class)->prefix('subscriptions/requests')->name('subscription-requests.')->whereUuid('subscriptionRequest')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('{subscriptionRequest}', 'show')->name('show');
    Route::post('{subscriptionRequest}/invoice', 'prepareInvoice')->name('invoice');
    Route::post('{subscriptionRequest}/accept', 'accept')->name('accept');
    Route::post('{subscriptionRequest}/reject', 'reject')->name('reject');
});

Route::controller(SubscriptionController::class)->prefix('subscriptions')->name('subscriptions.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{subscription}', 'show')->name('show');
    Route::get('{subscription}/history', 'history')->name('history');
    Route::post('{subscription}/close', 'close')->name('close');
    Route::post('{subscription}/deactivate', 'deactivate')->name('deactivate');
    Route::get('{subscription}/change', 'changeForm')->name('change.form');
    Route::post('{subscription}/change', 'change')->name('change');
});
