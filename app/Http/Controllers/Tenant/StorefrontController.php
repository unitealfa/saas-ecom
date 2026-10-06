<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Central\Domain;
use Illuminate\Http\Response;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;

class StorefrontController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): Response
    {
        $domain = DomainTenantResolver::$currentDomain;
        abort_unless($domain instanceof Domain && $domain->getAttribute('verification_status') === 2, 404);

        return response('Cette boutique est en préparation.', 503)
            ->header('Retry-After', '3600');
    }
}
