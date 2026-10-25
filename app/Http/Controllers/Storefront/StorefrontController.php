<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\VerificationStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureLocalDiagnostics;
use App\Models\Central\Domain;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;

class StorefrontController extends Controller
{
    public function __invoke(Request $request, EnsureLocalDiagnostics $diagnostics): Response
    {
        $domain = DomainTenantResolver::$currentDomain;

        abort_unless(
            $domain instanceof Domain && $domain->verification_status === VerificationStatusEnum::VERIFIED,
            404,
        );

        $message = 'Cette boutique est en préparation.';

        if ($diagnostics->isAllowed($request)) {
            $message .= "\nID de la boutique : ".tenant('id')
                ."\nID du propriétaire (compte central) : ".tenant('user_id');
        }

        return response($message, 503, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'Retry-After' => '3600',
        ]);
    }
}
