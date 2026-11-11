<?php

use App\Http\Controllers\Central\Admin\Operations\OperationsController;
use Illuminate\Support\Facades\Route;

Route::get('operations', OperationsController::class)->name('operations.index');
Route::post('operations/subscriptions/reconcile', [OperationsController::class, 'reconcileSubscriptions'])->name('operations.subscriptions.reconcile');
Route::post('operations/payments/reconcile', [OperationsController::class, 'reconcilePayments'])->name('operations.payments.reconcile');
