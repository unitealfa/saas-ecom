<?php

use App\Http\Controllers\Central\Admin\Subscriptions\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::controller(SubscriptionController::class)->prefix('subscriptions')->name('subscriptions.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('{subscription}', 'show')->name('show');
    Route::post('{subscription}/close', 'close')->name('close');
    Route::get('{subscription}/change', 'changeForm')->name('change.form');
    Route::post('{subscription}/change', 'change')->name('change');
});
