<?php

use App\Http\Controllers\Central\Admin\Subscriptions\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::controller(SubscriptionController::class)->prefix('subscriptions')->name('subscriptions.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('{subscription}', 'show')->name('show');
});
