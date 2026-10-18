<?php

namespace App\Http\Middleware\Tenant;

use App\Enums\Central\Tenants\StatusEnum;
use App\Enums\Central\Users\StatusEnum as UserStatus;
use App\Enums\VerificationStatusEnum;
use App\Models\Central\Domain;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAvailable
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $domain = Domain::where('domain', $request->getHost())->with(['tenant.owner'])->first();
        abort_if($domain === null || $domain->tenant === null || $domain->verification_status !== VerificationStatusEnum::VERIFIED, 404);
        $tenant = $domain->tenant;
        $owner = $tenant->owner;
        $unavailable = $owner === null || $owner->status !== UserStatus::ACTIVE
            || $tenant->status !== StatusEnum::ACTIVE;
        abort_if($unavailable && ! $request->routeIs('tenant.home'), 403);
        if ($unavailable || blank($tenant->schema_version) || $tenant->provisioned_at === null) {
            $message = 'Cette boutique est en préparation.';
            if ($owner?->status === UserStatus::ACTIVE
                && in_array($tenant->status, [StatusEnum::PROVISIONING, StatusEnum::PROVISIONING_FAILED, StatusEnum::ACTIVE], true)
                && app()->environment(['local', 'testing']) && config('app.debug')
                && in_array($request->ip(), ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true)) {
                $message .= "\nID de la boutique : ".$tenant->id."\nID du propriétaire (compte central) : ".$tenant->user_id;
            }

            return response($message, 503)->header('Content-Type', 'text/plain; charset=UTF-8')
                ->header('Cache-Control', 'no-store')->header('Retry-After', '3600');
        }

        return $next($request);
    }
}
