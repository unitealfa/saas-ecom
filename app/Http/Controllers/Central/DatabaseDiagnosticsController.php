<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Services\Central\DatabaseDiagnostics;
use Illuminate\Http\Response;

class DatabaseDiagnosticsController extends Controller
{
    public function __invoke(DatabaseDiagnostics $diagnostics): Response
    {
        return response()->view('central.database-diagnostics', $diagnostics->read())
            ->header('Cache-Control', 'private, no-store');
    }
}
