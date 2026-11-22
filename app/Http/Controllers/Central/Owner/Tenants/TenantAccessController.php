<?php

namespace App\Http\Controllers\Central\Owner\Tenants;

use App\Enums\Central\Deployments\StatusEnum as DeploymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Central\Deployments\TenantSchemaDeployment;
use App\Models\Central\Tenants\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

class TenantAccessController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant): RedirectResponse
    {
        $actor = $this->centralActor();
        abort_unless($tenant->user_id === $actor->id, 403);
        Gate::forUser($actor)->authorize('accessOwn', $tenant);
        if (TenantSchemaDeployment::where('tenant_id', $tenant->id)
            ->whereIn('status', [DeploymentStatus::PENDING, DeploymentStatus::RUNNING])->exists()) {
            return redirect()->route('dashboard')->with('warning', 'Cette boutique est en préparation.');
        }
        $domain = $tenant->domains()->where('is_primary', true)->firstOrFail();
        abort_unless($domain->isReadyAsPrimary(), 403);
        $nonce = $request->query('nonce');
        if ($nonce === null) {
            $path = URL::temporarySignedRoute('tenant.owner-login.start', now()->addMinute(), [
                'tenant_uuid' => $tenant->uuid,
            ], absolute: false);

            return redirect()->away(rtrim($domain->publicUrl(), '/').$path)
                ->withHeaders(['Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store']);
        }
        $request->validate(['nonce' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']]);
        $path = URL::temporarySignedRoute('tenant.owner-login', now()->addMinute(), [
            'nonce' => $nonce, 'owner' => $actor->uuid, 'tenant_uuid' => $tenant->uuid,
            'email_hash' => hash('sha256', $actor->email),
        ], absolute: false);

        return redirect()->away(rtrim($domain->publicUrl(), '/').$path)
            ->withHeaders(['Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store']);
    }
}
