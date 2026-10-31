<?php

use App\Http\Controllers\Central\Geography\GeographyLookupController;
use Illuminate\Support\Facades\Route;

Route::controller(GeographyLookupController::class)->prefix('reference/geography')->name('geography.lookup.')->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('{uuid}', 'show')->whereUuid('uuid')->name('show');
});
