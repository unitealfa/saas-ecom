
<?php

use App\Http\Controllers\Central\Admin\Billing\SaasDocumentDeliveryController;
use Illuminate\Support\Facades\Route;

Route::controller(SaasDocumentDeliveryController::class)
    ->prefix('billing/deliveries')
    ->name('deliveries.')
    ->whereUuid('delivery')
    ->group(function (): void {
        Route::get('/', 'index')->name('index');

        Route::post('/send', 'send')
            ->middleware('throttle:6,1')
            ->name('send');

        Route::get('/{delivery}', 'show')->name('show');

        Route::post('/{delivery}/retry', 'retry')
            ->middleware('throttle:3,1')
            ->name('retry');
    });
