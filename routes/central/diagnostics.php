<?php

use App\Http\Controllers\Central\Diagnostics\DatabaseDiagnosticsController;
use App\Http\Middleware\EnsureLocalDiagnostics;
use Illuminate\Support\Facades\Route;

Route::get('/_dev/database', DatabaseDiagnosticsController::class)
    ->middleware(EnsureLocalDiagnostics::class)->name('central.database-diagnostics');
