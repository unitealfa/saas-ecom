<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\VerificationStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Central\Domain;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;

class StorefrontController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $domain = DomainTenantResolver::$currentDomain;
        abort_unless($domain instanceof Domain && $domain->getAttribute('verification_status') === VerificationStatusEnum::VERIFIED, 404);

        $message = 'Cette boutique est en préparation.';

        if (app()->environment(['local', 'testing'])
            && config('app.debug')
            && in_array($request->ip(), ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true)) {
            $message .= "\nID de la boutique : ".tenant('id')
                ."\nID du propriétaire (compte central) : ".tenant('user_id');
        }

        return response($message, 503)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'no-store')
            ->header('Retry-After', '3600');
    }
}
