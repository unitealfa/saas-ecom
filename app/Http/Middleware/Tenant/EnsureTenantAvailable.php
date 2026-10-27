<?php

namespace App\Http\Middleware\Tenant;

use App\Enums\Central\Deployments\StatusEnum as DeploymentStatus;
use App\Enums\Central\Tenants\StatusEnum as TenantStatus;
use App\Enums\Central\Users\StatusEnum as UserStatus;
use App\Enums\VerificationStatusEnum;
use App\Http\Middleware\EnsureLocalDiagnostics;
use App\Models\Central\Deployments\TenantSchemaDeployment;
use App\Models\Central\Domains\Domain;
use App\Models\Central\Tenants\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAvailable
{
    private const array PROVISIONING_STATUSES = [
        TenantStatus::PROVISIONING,
        TenantStatus::PROVISIONING_FAILED,
        TenantStatus::ACTIVE,
    ];

    /** Un déploiement en attente ou en cours met la boutique en maintenance. */
    private const array DEPLOYMENT_OPEN = [DeploymentStatus::PENDING, DeploymentStatus::RUNNING];

    public function __construct(private EnsureLocalDiagnostics $diagnostics) {}

    public function handle(Request $request, Closure $next): Response
    {
        $domain = Domain::where('domain', $request->getHost())->with('tenant.owner')->first();

        abort_if(
            $domain === null
            || $domain->tenant === null
            || $domain->verification_status !== VerificationStatusEnum::VERIFIED,
            404,
        );

        $tenant = $domain->tenant;
        $usable = $this->isUsable($tenant);

        abort_if(! $usable && ! $request->routeIs('tenant.home'), 403);

        if (! $usable || ! $this->isProvisioned($tenant)) {
            return $this->preparingResponse($request, $tenant);
        }

        return $next($request);
    }

    private function isUsable(Tenant $tenant): bool
    {
        return $tenant->owner?->status === UserStatus::ACTIVE
            && $tenant->status === TenantStatus::ACTIVE;
    }

    private function isProvisioned(Tenant $tenant): bool
    {
        return filled($tenant->schema_version)
            && $tenant->provisioned_at !== null
            && ! $this->hasOpenDeployment($tenant);
    }

    private function hasOpenDeployment(Tenant $tenant): bool
    {
        return TenantSchemaDeployment::where('tenant_id', $tenant->id)
            ->whereIn('status', self::DEPLOYMENT_OPEN)
            ->exists();
    }

    private function preparingResponse(Request $request, Tenant $tenant): Response
    {
        $message = 'Cette boutique est en préparation.';

        if ($this->shouldExposeDebugInfo($request, $tenant)) {
            $message .= "\nID de la boutique : ".$tenant->id
                ."\nID du propriétaire (compte central) : ".$tenant->user_id;
        }

        return response($message, 503, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'Retry-After' => '3600',
        ]);
    }

    private function shouldExposeDebugInfo(Request $request, Tenant $tenant): bool
    {
        return $tenant->owner?->status === UserStatus::ACTIVE
            && in_array($tenant->status, self::PROVISIONING_STATUSES, true)
            && $this->diagnostics->isAllowed($request);
    }
}
