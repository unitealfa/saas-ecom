<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Tenant\DatabaseDiagnostics;
use Illuminate\Http\Response;

class DatabaseDiagnosticsController extends Controller
{
    public function __invoke(DatabaseDiagnostics $diagnostics): Response
    {
        return response()->view('tenant.database-diagnostics', $diagnostics->read())
            ->header('Cache-Control', 'private, no-store');
    }
}
