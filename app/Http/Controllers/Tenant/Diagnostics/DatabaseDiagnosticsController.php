<?php

namespace App\Http\Controllers\Tenant\Diagnostics;

use App\Http\Controllers\Controller;
use App\Services\Tenant\Diagnostics\DatabaseDiagnostics;
use Illuminate\Http\Response;

class DatabaseDiagnosticsController extends Controller
{
    public function __invoke(DatabaseDiagnostics $diagnostics): Response
    {
        return response()->view(
            'tenant.pages.diagnostics.database-diagnostics',
            $diagnostics->read(),
            200,
            ['Cache-Control' => 'private, no-store'],
        );
    }
}